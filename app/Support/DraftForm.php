<?php

namespace App\Support;

use App\Models\Draft;
use App\Models\Medicine;
use App\Models\Stock;
use App\Models\UnitType;

/**
 * Field map for the editable recovery forms on /drafts.
 *
 * Every name below matches the real application form and the validation rules
 * used by DraftFinalizer, so a corrected draft can always be finalized.
 */
class DraftForm
{
    private const MONTHS = [
        1 => 'يناير',
        2 => 'فبراير',
        3 => 'مارس',
        4 => 'إبريل',
        5 => 'مايو',
        6 => 'يونيو',
        7 => 'يوليو',
        8 => 'أغسطس',
        9 => 'سبتمبر',
        10 => 'أكتوبر',
        11 => 'نوفمبر',
        12 => 'ديسمبر',
    ];

    /**
     * Options shared by all drafts on the page. Loaded once per request.
     */
    public static function options(): array
    {
        $medicines = Medicine::orderBy('name')
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Medicine $medicine) => [$medicine->id => $medicine->name])
            ->all();

        $units = UnitType::orderBy('name')
            ->get(['id', 'name'])
            ->mapWithKeys(fn (UnitType $unit) => [$unit->id => $unit->name])
            ->all();

        $stocks = Stock::with('medicine')
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(function (Stock $stock) {
                $name = $stock->medicine?->name ?: ('دواء #' . $stock->medicine_id);
                $date = substr((string) $stock->stock_date, 0, 10);

                return [$stock->id => trim($name . ' — ' . $date . ' (#' . $stock->id . ')')];
            })
            ->all();

        return [
            'medicines' => $medicines,
            'units' => $units,
            'stocks' => $stocks,
        ];
    }

    /**
     * @return array{fields: array<int, array>, groups: array<int, array>}
     */
    public static function schema(Draft $draft, array $options): array
    {
        $payload = is_array($draft->payload) ? $draft->payload : [];

        return match ($draft->type) {
            'invoice' => self::invoice($payload, $options),
            'dispensed_medicine' => self::dispensed($payload, $options),
            'medicine' => self::medicine($payload, $options),
            'medicine_edit' => self::medicineEdit($payload, $options),
            'stock' => self::stock($payload, $options),
            'stock_edit' => self::stockEdit($payload, $options),
            'stock_transfer' => self::stockTransfer($payload),
            'unit' => self::unit($payload),
            'unit_edit' => self::unitEdit($payload, $options),
            default => ['fields' => [], 'groups' => []],
        };
    }

    private static function invoice(array $p, array $options): array
    {
        $columns = [
            self::field('medicine_id', 'الدواء', 'select', ['options' => $options['medicines'] ?? [], 'required' => true]),
            self::field('quantity', 'الكمية', 'number', ['required' => true, 'min' => 0, 'step' => 1]),
        ];

        return [
            'fields' => [
                self::field('referral_number', 'رقم التحويل (يدوي)', 'text', ['value' => self::value($p, 'referral_number'), 'required' => true]),
                self::field('invoice_date', 'التاريخ', 'date', ['value' => self::value($p, 'invoice_date')]),
                self::field('patient_name', 'اسم المريض (يدوي)', 'text', ['value' => self::value($p, 'patient_name')]),
            ],
            'groups' => [
                self::group('items', 'الأصناف', 'إضافة صنف', $columns, self::rows($p, 'items', $columns)),
            ],
        ];
    }

    private static function dispensed(array $p, array $options): array
    {
        $columns = [
            self::field('referral_number', 'الرقم (يدوي)', 'text'),
            self::field('dispense_date', 'التاريخ', 'date', ['required' => true]),
            self::field('medicine_id', 'الصنف', 'select', ['options' => $options['medicines'] ?? [], 'required' => true]),
            self::field('quantity', 'الكمية', 'number', ['required' => true, 'min' => 1, 'step' => 1]),
        ];

        return [
            'fields' => [],
            'groups' => [
                self::group('medicines', 'الأدوية المنصرفة', 'إضافة صف', $columns, self::rows($p, 'medicines', $columns)),
            ],
        ];
    }

    private static function medicine(array $p, array $options): array
    {
        $columns = [
            self::field('name', 'اسم الصنف', 'text', ['required' => true, 'placeholder' => 'مثال: باراسيتامول 500']),
            self::field('unit_type_id', 'الوحدة', 'select', ['options' => $options['units'] ?? [], 'required' => true]),
            self::field('price_hotline', 'سعر الخط الساخن', 'number', ['required' => true, 'min' => 0, 'step' => 'any', 'placeholder' => '1.25']),
            self::field('price_contract', 'سعر الارتباط', 'number', ['required' => true, 'min' => 0, 'step' => 'any', 'placeholder' => '0.95']),
            self::field('price_clinic', 'سعر العيادة', 'number', ['required' => true, 'min' => 0, 'step' => 'any', 'placeholder' => '1.10']),
        ];

        if (isset($p['medicines']) && is_array($p['medicines'])) {
            return [
                'fields' => [],
                'groups' => [
                    self::group('medicines', 'الأصناف', 'إضافة صنف آخر', $columns, self::rows($p, 'medicines', $columns)),
                ],
            ];
        }

        // Older drafts of this type were saved with flat fields.
        return [
            'fields' => array_map(
                fn (array $column) => self::field(
                    $column['name'],
                    $column['label'],
                    $column['type'],
                    array_merge($column, ['value' => self::value($p, $column['name'])])
                ),
                $columns
            ),
            'groups' => [],
        ];
    }

    private static function medicineEdit(array $p, array $options): array
    {
        return [
            'fields' => [
                self::field('medicine_id', 'الدواء', 'select', [
                    'options' => $options['medicines'] ?? [],
                    'value' => self::value($p, 'medicine_id'),
                    'required' => true,
                ]),
                self::field('name', 'اسم الصنف', 'text', ['value' => self::value($p, 'name'), 'required' => true]),
                self::field('unit_type_id', 'الوحدة', 'select', [
                    'options' => $options['units'] ?? [],
                    'value' => self::value($p, 'unit_type_id'),
                    'required' => true,
                ]),
                self::field('price_hotline', 'سعر الخط الساخن', 'number', ['value' => self::value($p, 'price_hotline'), 'required' => true, 'min' => 0, 'step' => 'any']),
                self::field('price_contract', 'سعر الارتباط', 'number', ['value' => self::value($p, 'price_contract'), 'required' => true, 'min' => 0, 'step' => 'any']),
                self::field('price_clinic', 'سعر العيادة', 'number', ['value' => self::value($p, 'price_clinic'), 'required' => true, 'min' => 0, 'step' => 'any']),
            ],
            'groups' => [],
        ];
    }

    private static function stock(array $p, array $options): array
    {
        return [
            'fields' => [
                self::field('stock_date', 'تاريخ الرصيد', 'date', ['value' => self::value($p, 'stock_date'), 'required' => true]),
                self::field('medicine_id', 'الدواء', 'select', [
                    'options' => $options['medicines'] ?? [],
                    'value' => self::value($p, 'medicine_id'),
                    'required' => true,
                ]),
                self::field('quantity', 'الكمية (رصيد أول الشهر)', 'number', [
                    'value' => self::value($p, 'quantity'),
                    'required' => true,
                    'min' => 0,
                    'step' => 1,
                    'placeholder' => '1000',
                ]),
            ],
            'groups' => [],
        ];
    }

    private static function stockEdit(array $p, array $options): array
    {
        return [
            'fields' => [
                self::field('stock_id', 'سجل الرصيد', 'select', [
                    'options' => $options['stocks'] ?? [],
                    'value' => self::value($p, 'stock_id'),
                    'required' => true,
                ]),
                self::field('quantity', 'رصيد أول الشهر', 'number', ['value' => self::value($p, 'quantity'), 'required' => true, 'min' => 0, 'step' => 1]),
                self::field('dispensed', 'المنصرف', 'number', ['value' => self::value($p, 'dispensed'), 'required' => true, 'min' => 0, 'step' => 1]),
            ],
            'groups' => [],
        ];
    }

    private static function stockTransfer(array $p): array
    {
        $years = array_combine(range(2026, 2050), range(2026, 2050));

        return [
            'fields' => [
                self::field('from', 'تاريخ من', 'date', ['value' => self::value($p, 'from'), 'required' => true]),
                self::field('to', 'تاريخ إلى', 'date', ['value' => self::value($p, 'to'), 'required' => true]),
                self::field('month', 'تحويل الرصيد لشهر', 'select', [
                    'options' => self::MONTHS,
                    'value' => self::value($p, 'month'),
                    'required' => true,
                ]),
                self::field('year', 'السنة', 'select', [
                    'options' => $years,
                    'value' => self::value($p, 'year'),
                    'required' => true,
                ]),
            ],
            'groups' => [],
        ];
    }

    private static function unit(array $p): array
    {
        return [
            'fields' => [
                self::field('name', 'اسم الوحدة', 'text', ['value' => self::value($p, 'name'), 'required' => true]),
            ],
            'groups' => [],
        ];
    }

    private static function unitEdit(array $p, array $options): array
    {
        return [
            'fields' => [
                self::field('unit_type_id', 'الوحدة', 'select', [
                    'options' => $options['units'] ?? [],
                    'value' => self::value($p, 'unit_type_id'),
                    'required' => true,
                ]),
                self::field('name', 'اسم الوحدة', 'text', ['value' => self::value($p, 'name'), 'required' => true]),
            ],
            'groups' => [],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     * @return array{fields: array, groups: array}
     */
    private static function group(string $name, string $label, string $addLabel, array $columns, array $rows): array
    {
        return [
            'name' => $name,
            'label' => $label,
            'add_label' => $addLabel,
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    private static function rows(array $payload, string $key, array $columns): array
    {
        $stored = isset($payload[$key]) && is_array($payload[$key]) ? $payload[$key] : [];
        $rows = [];

        foreach ($stored as $row) {
            $row = is_array($row) ? $row : [];
            $values = [];

            foreach ($columns as $column) {
                $values[$column['name']] = self::value($row, $column['name']);
            }

            $rows[] = $values;
        }

        // Keep an empty row visible so the user can fill the missing medicine
        // (the "medicines.N.medicine_id is required" case) instead of losing it.
        if ($rows === []) {
            $rows[] = array_fill_keys(array_column($columns, 'name'), '');
        }

        return $rows;
    }

    private static function field(string $name, string $label, string $type, array $extra = []): array
    {
        $defaults = [
            'name' => $name,
            'label' => $label,
            'type' => $type,
            'value' => '',
            'options' => [],
            'required' => false,
            'placeholder' => '',
            'min' => null,
            'step' => null,
        ];

        // Do not copy "value" from a column definition into every dynamic row.
        if ($type === 'select' || isset($extra['value'])) {
            $defaults['value'] = (string) ($extra['value'] ?? '');
        }

        return array_merge($defaults, $extra);
    }

    private static function value(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }
}
