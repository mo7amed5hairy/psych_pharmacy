<?php

namespace App\Services;

use App\Exceptions\DuplicateReferralException;
use App\Models\DispensedMedicine;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Medicine;
use App\Models\Stock;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Single source of truth for creating / updating invoices.
 * Shared by InvoiceController and the drafts recovery pipeline.
 */
class InvoiceService
{
    public static function rules(): array
    {
        return [
            'patient_name' => 'nullable|string|max:200',
            'referral_number' => 'required|string|max:50',
            'invoice_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'required|exists:medicines,id',
            'items.*.quantity' => 'required|integer|min:0',
        ];
    }

    /**
     * Validate a draft-style payload (used by the recovery pipeline).
     */
    public static function validatePayload(array $payload): array
    {
        return Validator::make($payload, self::rules())->validate();
    }

    public function create(User $user, array $data, ?string $clientUuid = null): Invoice
    {
        if ($clientUuid) {
            $existing = Invoice::where('client_uuid', $clientUuid)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $ref = $data['referral_number'];
        $invoiceDate = !empty($data['invoice_date']) ? $data['invoice_date'] : now()->format('Y-m-d');

        if ($this->referralAlreadyUsed($user, $ref, $invoiceDate)) {
            throw new DuplicateReferralException("الرقم \"{$ref}\" مستخدم مسبقاً في هذا التاريخ (لمنع التكرار في نفس اليوم)");
        }

        $monthStart = Carbon::parse($invoiceDate)->startOfMonth()->format('Y-m-d');

        return DB::transaction(function () use ($user, $data, $invoiceDate, $monthStart, $clientUuid) {
            $invoice = Invoice::create([
                'client_uuid' => $clientUuid,
                'user_id' => $user->id,
                'patient_name' => $data['patient_name'] ?? '-',
                'referral_number' => $data['referral_number'],
            ]);

            if (!empty($data['invoice_date'])) {
                $invoice->created_at = $data['invoice_date'];
                $invoice->save();
            }

            // الشهر الذي سيتم الخصم منه = شهر تاريخ الفاتورة (وليس الشهر الحالي)
            $this->ensureMonthTransferred($user, $monthStart);

            foreach ($data['items'] as $item) {
                $this->applyItem($user, $invoice, $item, $monthStart);
            }

            return $invoice;
        });
    }

    public function update(User $user, Invoice $invoice, array $data): Invoice
    {
        // شهري الخصم: القديم من تاريخ الفاتورة الحالي، والجديد من تاريخ الفاتورة المُرسَل
        $oldMonthStart = $invoice->created_at
            ? Carbon::parse($invoice->created_at)->startOfMonth()->format('Y-m-d')
            : now()->startOfMonth()->format('Y-m-d');

        $newMonthStart = !empty($data['invoice_date'])
            ? Carbon::parse($data['invoice_date'])->startOfMonth()->format('Y-m-d')
            : $oldMonthStart;

        return DB::transaction(function () use ($user, $invoice, $data, $oldMonthStart, $newMonthStart) {
            // Restore stock for old items (from the OLD invoice date's month)
            foreach ($invoice->items as $oldItem) {
                $stock = Stock::where('user_id', $user->id)
                    ->where('medicine_id', $oldItem->medicine_id)
                    ->where('stock_date', $oldMonthStart)
                    ->lockForUpdate()
                    ->first();

                if ($stock) {
                    $stock->decrement('dispensed', $oldItem->quantity);
                }
            }

            $invoice->items()->delete();

            $invoice->update([
                'patient_name' => $data['patient_name'] ?? '-',
                'referral_number' => $data['referral_number'],
            ]);

            if (!empty($data['invoice_date'])) {
                $invoice->created_at = $data['invoice_date'];
                $invoice->save();
            }

            $this->ensureMonthTransferred($user, $newMonthStart);

            foreach ($data['items'] as $item) {
                $this->applyItem($user, $invoice, $item, $newMonthStart);
            }

            return $invoice;
        });
    }

    public function referralAlreadyUsed(User $user, string $ref, string $invoiceDate): bool
    {
        return Invoice::where('referral_number', $ref)
            ->where('user_id', $user->id)
            ->whereDate('created_at', $invoiceDate)
            ->exists()
            || DispensedMedicine::where('referral_number', $ref)
                ->where('user_id', $user->id)
                ->whereDate('dispense_date', $invoiceDate)
                ->exists();
    }

    /**
     * يتأكد من أن المستخدم قام بتحويل أرصدة إلى الشهر المطلوب (بوجود أي سجل رصيد لهذا الشهر).
     */
    public function ensureMonthTransferred(User $user, string $monthStart): void
    {
        $hasStock = Stock::where('user_id', $user->id)
            ->where('stock_date', $monthStart)
            ->exists();

        if (!$hasStock) {
            throw new DomainException('لم يتم تحويل أرصدة للشهر الذي تريد الصرف منه');
        }
    }

    private function applyItem(User $user, Invoice $invoice, array $item, string $monthStart): void
    {
        if ((int) $item['quantity'] === 0) {
            return;
        }

        $medicine = Medicine::find($item['medicine_id']);

        if (!$medicine) {
            throw new DomainException('الدواء غير موجود');
        }

        $stock = Stock::where('user_id', $user->id)
            ->where('medicine_id', $item['medicine_id'])
            ->where('stock_date', $monthStart)
            ->lockForUpdate()
            ->first();

        $remaining = $stock ? ($stock->quantity - $stock->dispensed) : 0;

        if (!$stock || $remaining < $item['quantity']) {
            throw new DomainException("الكمية غير كافية من {$medicine->name} (الرصيد المتاح: {$remaining})");
        }

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'medicine_id' => $item['medicine_id'],
            'quantity' => $item['quantity'],
            'price_at_dispense' => $medicine->price_hotline,
        ]);

        $stock->increment('dispensed', $item['quantity']);
    }
}
