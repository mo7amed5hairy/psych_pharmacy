<?php

namespace App\Services;

use App\Models\DispensedMedicine;
use App\Models\Medicine;
use App\Models\Stock;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Single source of truth for dispensed medicine records.
 */
class DispensedMedicineService
{
    public static function rules(): array
    {
        return [
            'medicines' => 'required|array|min:1',
            'medicines.*.medicine_id' => 'required|exists:medicines,id',
            'medicines.*.quantity' => 'required|integer|min:1',
            'medicines.*.dispense_date' => 'required|date',
            'medicines.*.referral_number' => 'nullable|string|max:50',
        ];
    }

    public static function validatePayload(array $payload): array
    {
        return Validator::make($payload, self::rules())->validate();
    }

    public function createMany(User $user, array $medicines): int
    {
        return DB::transaction(function () use ($user, $medicines) {
            $successCount = 0;

            foreach ($medicines as $item) {
                $medicine = Medicine::find($item['medicine_id']);

                if (!$medicine) {
                    throw new DomainException('الدواء غير موجود');
                }

                $monthStart = Carbon::parse($item['dispense_date'])->startOfMonth()->format('Y-m-d');
                $this->ensureMonthTransferred($user, $monthStart);

                $stock = Stock::where('user_id', $user->id)
                    ->where('medicine_id', $item['medicine_id'])
                    ->where('stock_date', $monthStart)
                    ->lockForUpdate()
                    ->first();

                $remaining = $stock ? ($stock->quantity - $stock->dispensed) : 0;

                if (!$stock || $remaining < $item['quantity']) {
                    throw new DomainException("الكمية غير كافية من {$medicine->name} (الرصيد المتاح: {$remaining})");
                }

                DispensedMedicine::create([
                    'user_id' => $user->id,
                    'medicine_id' => $item['medicine_id'],
                    'referral_number' => $item['referral_number'] ?? null,
                    'dispense_date' => $item['dispense_date'],
                    'quantity' => $item['quantity'],
                ]);

                $stock->increment('dispensed', $item['quantity']);

                $successCount++;
            }

            return $successCount;
        });
    }

    public function update(User $user, DispensedMedicine $dispensed, array $item): void
    {
        DB::transaction(function () use ($user, $dispensed, $item) {
            // شهر المخزون القديم = شهر تاريخ الصرفية الحالي
            $oldMonthStart = Carbon::parse($dispensed->dispense_date)->startOfMonth()->format('Y-m-d');

            $oldStock = Stock::where('user_id', $user->id)
                ->where('medicine_id', $dispensed->medicine_id)
                ->where('stock_date', $oldMonthStart)
                ->first();

            if ($oldStock) {
                $oldStock->decrement('dispensed', $dispensed->quantity);
            }

            // شهر المخزون الجديد = شهر التاريخ الجديد للصرفية
            $newMonthStart = Carbon::parse($item['dispense_date'])->startOfMonth()->format('Y-m-d');

            $this->ensureMonthTransferred($user, $newMonthStart);

            $newStock = Stock::where('user_id', $user->id)
                ->where('medicine_id', $item['medicine_id'])
                ->where('stock_date', $newMonthStart)
                ->first();

            $remaining = $newStock ? ($newStock->quantity - $newStock->dispensed) : 0;

            if (!$newStock || $remaining < $item['quantity']) {
                throw new DomainException('الكمية غير كافية من الدواء (الرصيد المتاح: ' . $remaining . ')');
            }

            $dispensed->update($item);

            $newStock->increment('dispensed', $item['quantity']);
        });
    }

    public function delete(User $user, DispensedMedicine $dispensed): void
    {
        DB::transaction(function () use ($user, $dispensed) {
            $monthStart = Carbon::parse($dispensed->dispense_date)->startOfMonth()->format('Y-m-d');

            $stock = Stock::where('user_id', $user->id)
                ->where('medicine_id', $dispensed->medicine_id)
                ->where('stock_date', $monthStart)
                ->first();

            if ($stock) {
                $stock->decrement('dispensed', $dispensed->quantity);
            }

            $dispensed->delete();
        });
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
}
