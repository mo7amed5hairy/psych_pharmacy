<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\UnitType;
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
            $validated = $request->validate([
                'medicines' => 'required|array',
                'medicines.*.name' => 'required|string|max:200',
                'medicines.*.unit_type_id' => 'required|exists:unit_types,id',
                'medicines.*.price_hotline' => 'required|numeric|min:0',
                'medicines.*.price_contract' => 'required|numeric|min:0',
                'medicines.*.price_clinic' => 'required|numeric|min:0',
            ]);

            foreach ($validated['medicines'] as $medicineData) {
                Medicine::create($medicineData);
            }

            $count = count($validated['medicines']);
            return redirect()->route('medicines.index')->with('success', "تم إضافة {$count} أصناف بنجاح");
        }

        // Single medicine add
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'unit_type_id' => 'required|exists:unit_types,id',
            'price_hotline' => 'required|numeric|min:0',
            'price_contract' => 'required|numeric|min:0',
            'price_clinic' => 'required|numeric|min:0',
        ]);

        Medicine::create($validated);

        return redirect()->route('medicines.index')->with('success', 'تم إضافة الدواء بنجاح');
    }

    public function update(Request $request, Medicine $medicine)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'unit_type_id' => 'required|exists:unit_types,id',
            'price_hotline' => 'required|numeric|min:0',
            'price_contract' => 'required|numeric|min:0',
            'price_clinic' => 'required|numeric|min:0',
        ]);

        $medicine->update($validated);

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
