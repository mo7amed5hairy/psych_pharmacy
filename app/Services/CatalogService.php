<?php

namespace App\Services;

use App\Models\Medicine;
use App\Models\UnitType;
use Illuminate\Support\Facades\Validator;

/**
 * Small helpers for the catalogue tables (medicines & unit types),
 * shared by their controllers and the drafts recovery pipeline.
 */
class CatalogService
{
    public static function medicineRules(): array
    {
        return [
            'name' => 'required|string|max:200',
            'unit_type_id' => 'required|exists:unit_types,id',
            'price_hotline' => 'required|numeric|min:0',
            'price_contract' => 'required|numeric|min:0',
            'price_clinic' => 'required|numeric|min:0',
        ];
    }

    public static function bulkMedicineRules(): array
    {
        return [
            'medicines' => 'required|array|min:1',
            'medicines.*.name' => 'required|string|max:200',
            'medicines.*.unit_type_id' => 'required|exists:unit_types,id',
            'medicines.*.price_hotline' => 'required|numeric|min:0',
            'medicines.*.price_contract' => 'required|numeric|min:0',
            'medicines.*.price_clinic' => 'required|numeric|min:0',
        ];
    }

    public static function unitRules(?UnitType $unit = null): array
    {
        $unique = $unit
            ? 'unique:unit_types,name,' . $unit->id
            : 'unique:unit_types';

        return [
            'name' => 'required|string|max:50|' . $unique,
        ];
    }

    public static function validateMedicinePayload(array $payload): array
    {
        return Validator::make($payload, self::medicineRules())->validate();
    }

    public static function validateBulkMedicinePayload(array $payload): array
    {
        return Validator::make($payload, self::bulkMedicineRules())->validate();
    }

    public static function validateUnitPayload(array $payload, ?UnitType $unit = null): array
    {
        return Validator::make($payload, self::unitRules($unit))->validate();
    }

    public static function createMedicine(array $data): Medicine
    {
        return Medicine::create($data);
    }

    /**
     * @return int number of created medicines
     */
    public static function createMedicines(array $rows): int
    {
        foreach ($rows as $row) {
            Medicine::create($row);
        }

        return count($rows);
    }

    public static function updateMedicine(Medicine $medicine, array $data): Medicine
    {
        $medicine->update($data);

        return $medicine;
    }

    public static function createUnit(array $data): UnitType
    {
        return UnitType::create($data);
    }

    public static function updateUnit(UnitType $unit, array $data): UnitType
    {
        $unit->update($data);

        return $unit;
    }
}
