<?php

namespace App\Http\Controllers;

use App\Models\DispensedMedicine;
use App\Models\Medicine;
use App\Services\DispensedMedicineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DispensedMedicineController extends Controller
{
    public function __construct(private DispensedMedicineService $dispensedMedicines)
    {
    }

    public function index(Request $request)
    {
        $query = DispensedMedicine::with(['medicine.unitType', 'user'])
            ->where('user_id', Auth::id());

        // Filter by date range if provided
        if ($request->filled('from_date')) {
            $query->where('dispense_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->where('dispense_date', '<=', $request->to_date);
        }

        // Filter by medicine if provided
        if ($request->filled('medicine_id')) {
            $query->where('medicine_id', $request->medicine_id);
        }

        $dispensedMedicines = $query->orderBy('dispense_date', 'desc')->get();
        $medicines = Medicine::with('unitType')->get();

        return view('dispensed-medicines.index', compact('dispensedMedicines', 'medicines'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate(DispensedMedicineService::rules());

        try {
            $successCount = $this->dispensedMedicines->createMany(Auth::user(), $validated['medicines']);

            return response()->json([
                'status' => 'success',
                'message' => "تم تسجيل {$successCount} صنف بنجاح",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $dispensed = DispensedMedicine::where('user_id', Auth::id())->findOrFail($id);

            $validator = Validator::make($request->all(), DispensedMedicineService::rules());

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            $item = $validator->validated()['medicines'][0];

            $this->dispensedMedicines->update(Auth::user(), $dispensed, $item);

            return response()->json([
                'status' => 'success',
                'message' => 'تم تعديل الصرفية بنجاح',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function destroy(DispensedMedicine $dispensedMedicine)
    {
        // Check if user owns this record
        if ($dispensedMedicine->user_id !== Auth::id()) {
            abort(403);
        }

        $this->dispensedMedicines->delete(Auth::user(), $dispensedMedicine);

        return redirect()->route('dispensed-medicines.index')->with('success', 'تم حذف السجل بنجاح');
    }
}
