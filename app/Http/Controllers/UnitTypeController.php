<?php

namespace App\Http\Controllers;

use App\Models\UnitType;
use App\Services\CatalogService;
use Illuminate\Http\Request;

class UnitTypeController extends Controller
{
    public function index()
    {
        $unitTypes = UnitType::orderBy('name')->get();

        return view('units.index', compact('unitTypes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate(CatalogService::unitRules());

        CatalogService::createUnit($validated);

        return redirect()->route('units.index')->with('success', 'تم إضافة الوحدة بنجاح');
    }

    public function update(Request $request, UnitType $unitType)
    {
        $validated = $request->validate(CatalogService::unitRules($unitType));

        CatalogService::updateUnit($unitType, $validated);

        return redirect()->route('units.index')->with('success', 'تم تحديث الوحدة بنجاح');
    }

    public function destroy(UnitType $unitType)
    {
        $unitType->delete();

        return redirect()->route('units.index')->with('success', 'تم حذف الوحدة بنجاح');
    }
}
