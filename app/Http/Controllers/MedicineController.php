<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\UnitType;
use App\Services\CatalogService;
use Illuminate\Http\Request;

class MedicineController extends Controller
{
    public function index()
    {
        $medicines = Medicine::with('unitType')
            ->orderBy('name')
            ->get();

        $unitTypes = UnitType::all();

        return view('medicines.index', compact('medicines', 'unitTypes'));
    }

    public function store(Request $request)
    {
        // Check if bulk insert (array of medicines)
        if ($request->has('medicines')) {
            $validated = $request->validate(CatalogService::bulkMedicineRules());

            $count = CatalogService::createMedicines($validated['medicines']);

            return redirect()->route('medicines.index')->with('success', "تم إضافة {$count} أصناف بنجاح");
        }

        // Single medicine add
        $validated = $request->validate(CatalogService::medicineRules());

        CatalogService::createMedicine($validated);

        return redirect()->route('medicines.index')->with('success', 'تم إضافة الدواء بنجاح');
    }

    public function update(Request $request, Medicine $medicine)
    {
        $validated = $request->validate(CatalogService::medicineRules());

        CatalogService::updateMedicine($medicine, $validated);

        return redirect()->route('medicines.index')->with('success', 'تم تحديث الدواء بنجاح');
    }

    public function destroy(Medicine $medicine)
    {
        $medicine->delete();

        return redirect()->route('medicines.index')->with('success', 'تم حذف الدواء بنجاح');
    }

    public function search(Request $request)
    {
        $query = $request->get('q');
        $medicines = Medicine::where('name', 'like', "%{$query}%")
            ->with('unitType')
            ->limit(10)
            ->get();

        return response()->json($medicines);
    }
}
