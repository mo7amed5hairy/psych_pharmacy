<?php

namespace App\Support;

use App\Models\Draft;
use App\Models\Medicine;

/**
 * Turns a stored draft payload into readable label/value rows for the
 * admin recovery screen. Medicine ids are resolved to names here (display
 * time only) so the stored payload stays small.
 */
class DraftPresenter
{
    private static array $medicineNames = [];

    public static function summary(Draft $draft): array
    {
        $payload = $draft->payload ?? [];

        return match ($draft->type) {
            'invoice' => self::invoice($payload),
            'dispensed_medicine' => self::dispensed($payload),
            'medicine' => self::medicine($payload),
            'medicine_edit' => self::medicineEdit($payload),
            'stock' => self::stock($payload),
            'stock_edit' => self::stockEdit($payload),
            'stock_transfer' => self::transfer($payload),
            'unit', 'unit_edit' => self::unit($payload),
            default => [],
        };
    }

    private static function invoice(array $p): array
    {
        $items = is_array($p['items'] ?? null) ? $p['items'] : [];
        $qty = 0;
        $lines = [];

        foreach ($items as $item) {
            $q = (int) ($item['quantity'] ?? 0);
            $qty += $q;
            $lines[] = self::medicineName($item['medicine_id'] ?? null) . ' × ' . $q;
        }

        return array_values(array_filter([
            self::row('رقم التحويل', $p['referral_number'] ?? ''),
            self::row('اسم المريض', $p['patient_name'] ?? ''),
            self::row('تاريخ الفاتورة', $p['invoice_date'] ?? ''),
            self::row('عدد الأصناف', count($items)),
            self::row('إجمالي الكميات', $qty),
            self::row('الأصناف', $lines ? implode('، ', $lines) : 'لا توجد أصناف'),
        ], fn ($row) => $row !== null));
    }

    private static function dispensed(array $p): array
    {
        $rows = is_array($p['medicines'] ?? null) ? $p['medicines'] : [];
        $lines = [];

        foreach ($rows as $row) {
            $lines[] = self::medicineName($row['medicine_id'] ?? null)
                . ' × ' . (int) ($row['quantity'] ?? 0)
                . (!empty($row['dispense_date']) ? ' (' . $row['dispense_date'] . ')' : '');
        }

        return array_values(array_filter([
            self::row('عدد الصفوف', count($rows)),
            self::row('التفاصيل', $lines ? implode('، ', $lines) : 'لا توجد صفوف'),
        ], fn ($row) => $row !== null));
    }

    private static function medicine(array $p): array
    {
        if (isset($p['medicines']) && is_array($p['medicines'])) {
            $lines = [];
            foreach ($p['medicines'] as $row) {
                $lines[] = trim((string) ($row['name'] ?? ''));
            }

            return array_values(array_filter([
                self::row('عدد الأصناف', count($p['medicines'])),
                self::row('الأصناف', $lines ? implode('، ', array_filter($lines)) : ''),
            ], fn ($row) => $row !== null));
        }

        return self::medicineFields($p);
    }

    private static function medicineEdit(array $p): array
    {
        return array_merge(
            [self::row('رقم الدواء', $p['medicine_id'] ?? '')],
            self::medicineFields($p)
        );
    }

    private static function medicineFields(array $p): array
    {
        return array_values(array_filter([
            self::row('الاسم', $p['name'] ?? ''),
            self::row('سعر الخط الساخن', $p['price_hotline'] ?? ''),
            self::row('سعر التعاقد', $p['price_contract'] ?? ''),
            self::row('سعر العيادة', $p['price_clinic'] ?? ''),
        ], fn ($row) => $row !== null));
    }

    private static function stock(array $p): array
    {
        return array_values(array_filter([
            self::row('الدواء', self::medicineName($p['medicine_id'] ?? null)),
            self::row('الكمية', $p['quantity'] ?? ''),
            self::row('تاريخ الرصيد', $p['stock_date'] ?? ''),
        ], fn ($row) => $row !== null));
    }

    private static function stockEdit(array $p): array
    {
        $stock = !empty($p['stock_id']) ? \App\Models\Stock::find($p['stock_id']) : null;

        return array_values(array_filter([
            self::row('رقم السجل', $p['stock_id'] ?? ''),
            self::row('الدواء', $stock?->medicine?->name ?? ''),
            self::row('رصيد أول الشهر', $p['quantity'] ?? ''),
            self::row('المنصرف', $p['dispensed'] ?? ''),
        ], fn ($row) => $row !== null));
    }

    private static function transfer(array $p): array
    {
        return array_values(array_filter([
            self::row('من تاريخ', $p['from'] ?? ''),
            self::row('إلى تاريخ', $p['to'] ?? ''),
            self::row('الشهر المستهدف', (($p['month'] ?? '') !== '' ? $p['month'] : '') . ' / ' . ($p['year'] ?? '')),
        ], fn ($row) => $row !== null));
    }

    private static function unit(array $p): array
    {
        return array_values(array_filter([
            self::row('رقم الوحدة', $p['unit_type_id'] ?? ''),
            self::row('اسم الوحدة', $p['name'] ?? ''),
        ], fn ($row) => $row !== null));
    }

    private static function row(string $label, $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ['label' => $label, 'value' => (string) $value];
    }

    private static function medicineName($id): string
    {
        $id = (int) $id;

        if ($id <= 0) {
            return 'دواء غير محدد';
        }

        if (!array_key_exists($id, self::$medicineNames)) {
            self::$medicineNames[$id] = Medicine::whereKey($id)->value('name') ?: ('#' . $id);
        }

        return self::$medicineNames[$id];
    }
}
