<?php

namespace App\Services;

use App\Models\Draft;
use App\Models\Medicine;
use App\Models\Stock;
use App\Models\UnitType;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Writes a stored draft into the real database tables.
 *
 * Used by the admin recovery screen only. Normal user submissions are
 * written by their own controllers (the draft is then marked finalized
 * without replaying it, so nothing is ever inserted twice).
 */
class DraftFinalizer
{
    public function __construct(
        private InvoiceService $invoices,
        private DispensedMedicineService $dispensed,
    ) {
    }

    /**
     * @throws DomainException|Throwable when the draft cannot be applied
     */
    public function finalize(Draft $draft, User $actor): string
    {
        if (!$draft->isPending()) {
            return $draft->result ?: 'تم حفظ هذه المسودة مسبقاً';
        }

        try {
            $message = DB::transaction(function () use ($draft, $actor) {
                /** @var Draft $locked */
                $locked = Draft::whereKey($draft->getKey())->lockForUpdate()->firstOrFail();

                if (!$locked->isPending()) {
                    return $locked->result ?: 'تم حفظ هذه المسودة مسبقاً';
                }

                $message = $this->apply($locked, $locked->user, $locked->payload ?? []);

                $locked->forceFill([
                    'status' => Draft::STATUS_FINALIZED,
                    'finalized_at' => now(),
                    'finalized_by' => $actor->id,
                    'result' => $message,
                ])->save();

                return $message;
            });

            $draft->refresh();

            return $message;
        } catch (ValidationException $e) {
            $message = $e->validator->errors()->first();
            Draft::whereKey($draft->getKey())->update(['result' => 'فشل الحفظ: ' . $message]);
            $draft->refresh();

            throw new DomainException($message, 0, $e);
        } catch (Throwable $e) {
            Draft::whereKey($draft->getKey())->update(['result' => 'فشل الحفظ: ' . $e->getMessage()]);
            $draft->refresh();

            throw $e;
        }
    }

    private function apply(Draft $draft, User $user, array $payload): string
    {
        return match ($draft->type) {
            'invoice' => $this->applyInvoice($draft, $user, $payload),
            'dispensed_medicine' => $this->applyDispensed($user, $payload),
            'medicine' => $this->applyMedicine($payload),
            'medicine_edit' => $this->applyMedicineEdit($payload),
            'stock' => $this->applyStock($user, $payload),
            'stock_edit' => $this->applyStockEdit($user, $payload),
            'stock_transfer' => $this->applyStockTransfer($user, $payload),
            'unit' => $this->applyUnit($payload),
            'unit_edit' => $this->applyUnitEdit($payload),
            default => throw new DomainException('نوع المسودة غير مدعوم: ' . $draft->type),
        };
    }

    private function applyInvoice(Draft $draft, User $user, array $payload): string
    {
        $data = InvoiceService::validatePayload($payload);

        // client_uuid = draft uuid: a second attempt can never create a duplicate invoice.
        $invoice = $this->invoices->create($user, $data, $draft->uuid);

        return "تم حفظ الفاتورة رقم {$invoice->referral_number} (رقم داخلي {$invoice->id})";
    }

    private function applyDispensed(User $user, array $payload): string
    {
        $data = DispensedMedicineService::validatePayload($payload);
        $count = $this->dispensed->createMany($user, $data['medicines']);

        return "تم حفظ {$count} صنف في سجل الأدوية المنصرفة";
    }

    private function applyMedicine(array $payload): string
    {
        if (isset($payload['medicines']) && is_array($payload['medicines'])) {
            $data = CatalogService::validateBulkMedicinePayload($payload);
            $count = CatalogService::createMedicines($data['medicines']);

            return "تم إضافة {$count} دواء إلى قاموس الأدوية";
        }

        $data = CatalogService::validateMedicinePayload($payload);
        $medicine = CatalogService::createMedicine($data);

        return "تم إضافة الدواء: {$medicine->name}";
    }

    private function applyMedicineEdit(array $payload): string
    {
        $payload = $this->withId($payload, 'medicine_id');

        $medicine = Medicine::find($payload['medicine_id']);

        if (!$medicine) {
            throw new DomainException('الدواء المطلوب تعديله غير موجود');
        }

        $data = CatalogService::validateMedicinePayload($payload);
        CatalogService::updateMedicine($medicine, $data);

        return "تم تعديل الدواء: {$medicine->name}";
    }

    private function applyStock(User $user, array $payload): string
    {
        $data = StockService::validateAddPayload($payload);

        $stock = StockService::addBalance(
            $user,
            (int) $data['medicine_id'],
            (int) $data['quantity'],
            (string) $data['stock_date']
        );

        $name = $stock->medicine->name ?? ('#' . $data['medicine_id']);

        return "تم إضافة رصيد {$name} بمقدار {$data['quantity']}";
    }

    private function applyStockEdit(User $user, array $payload): string
    {
        $payload = $this->withId($payload, 'stock_id');

        $stock = Stock::find($payload['stock_id']);

        if (!$stock) {
            throw new DomainException('سجل الرصيد المطلوب تعديله غير موجود');
        }

        $data = StockService::validateEditPayload($payload);
        StockService::updateBalance($user, $stock, (int) $data['quantity'], (int) $data['dispensed']);

        $name = $stock->medicine->name ?? ('#' . $stock->medicine_id);

        return "تم تعديل رصيد {$name}";
    }

    private function applyStockTransfer(User $user, array $payload): string
    {
        $data = StockService::validateTransferPayload($payload);

        $result = StockService::transfer(
            $user,
            (string) $data['from'],
            (string) $data['to'],
            (int) $data['month'],
            (int) $data['year']
        );

        return "تم تحويل {$result['count']} صنف (إجمالي {$result['qty']}) إلى شهر {$result['label']}";
    }

    private function applyUnit(array $payload): string
    {
        $data = CatalogService::validateUnitPayload($payload);
        $unit = CatalogService::createUnit($data);

        return "تم إضافة الوحدة: {$unit->name}";
    }

    private function applyUnitEdit(array $payload): string
    {
        $payload = $this->withId($payload, 'unit_type_id');

        $unit = UnitType::find($payload['unit_type_id']);

        if (!$unit) {
            throw new DomainException('الوحدة المطلوب تعديلها غير موجودة');
        }

        $data = CatalogService::validateUnitPayload($payload, $unit);
        CatalogService::updateUnit($unit, $data);

        return "تم تعديل الوحدة: {$unit->name}";
    }

    private function withId(array $payload, string $key): array
    {
        if (empty($payload[$key])) {
            throw new DomainException('المسودة لا تحتوي على معرّف السجل المطلوب');
        }

        $payload[$key] = (int) $payload[$key];

        return $payload;
    }
}
