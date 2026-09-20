<?php

namespace App\Http\Controllers;

use App\Models\DispensedMedicine;
use App\Models\Medicine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DispensedMedicineController extends Controller
{
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
        $validated = $request->validate([
            'medicines' => 'required|array|min:1',

            'medicines.*.medicine_id' => 'required|exists:medicines,id',
            'medicines.*.quantity' => 'required|integer|min:1',
            'medicines.*.dispense_date' => 'required|date',
            'medicines.*.referral_number' => 'nullable|string|max:50',
        ]);

        DB::beginTransaction();

        try {

            $successCount = 0;

            foreach ($validated['medicines'] as $item) {

                /**
                 * 2) هات الدواء
                 */
                $medicine = Medicine::find($item['medicine_id']);

                if (!$medicine) {
                    throw new \Exception("الدواء غير موجود");
                }

                /**
                 * 3) هات المخزون الخاص بالمستخدم فقط لهذا الشهر
                 */
                $monthStart = \Carbon\Carbon::parse($item['dispense_date'])->startOfMonth()->format('Y-m-d');
                $this->ensureMonthTransferred($monthStart);

                $stock = \App\Models\Stock::where('user_id', Auth::id())
                    ->where('medicine_id', $item['medicine_id'])
                    ->where('stock_date', $monthStart)
                    ->lockForUpdate()
                    ->first();

                /**
                 * 4) التحقق من الكمية
                 */
                $remaining = $stock ? ($stock->quantity - $stock->dispensed) : 0;
                if (!$stock || $remaining < $item['quantity']) {
                    throw new \Exception(
                        "الكمية غير كافية من {$medicine->name} (الرصيد المتاح: {$remaining})"
                    );
                }

                /**
                 * 5) إنشاء سجل الصرف
                 */
                DispensedMedicine::create([
                    'user_id' => Auth::id(),
                    'medicine_id' => $item['medicine_id'],
                    'referral_number' => $item['referral_number'] ?? null,
                    'dispense_date' => $item['dispense_date'],
                    'quantity' => $item['quantity'],
                ]);

                /**
                 * 6) إضافة للمنصرف في المخزون
                 */
                $stock->increment('dispensed', $item['quantity']);

                $successCount++;
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "تم تسجيل {$successCount} صنف بنجاح"
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 422);
        }
    }


    public function update(Request $request, $id)
    {
        try {

            $dispensed = DispensedMedicine::where('user_id', Auth::id())
                ->findOrFail($id);

            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'medicines' => 'required|array',
                'medicines.*.medicine_id' => 'required|exists:medicines,id',
                'medicines.*.quantity' => 'required|integer|min:1',
                'medicines.*.dispense_date' => 'required|date',
                'medicines.*.referral_number' => 'nullable|string|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()->first()
                ], 422);
            }

            $validated = $validator->validated();
            $item = $validated['medicines'][0];

            DB::transaction(function () use ($dispensed, $item) {

                // شهر المخزون القديم = شهر تاريخ الصرفية الحالي
                $oldMonthStart = \Carbon\Carbon::parse($dispensed->dispense_date)->startOfMonth()->format('Y-m-d');

                $oldStock = \App\Models\Stock::where('user_id', Auth::id())
                    ->where('medicine_id', $dispensed->medicine_id)
                    ->where('stock_date', $oldMonthStart)
                    ->first();

                if ($oldStock) {
                    $oldStock->decrement('dispensed', $dispensed->quantity);
                }

                // شهر المخزون الجديد = شهر التاريخ الجديد للصرفية
                $newMonthStart = \Carbon\Carbon::parse($item['dispense_date'])->startOfMonth()->format('Y-m-d');

                $this->ensureMonthTransferred($newMonthStart);

                $newStock = \App\Models\Stock::where('user_id', Auth::id())
                    ->where('medicine_id', $item['medicine_id'])
                    ->where('stock_date', $newMonthStart)
                    ->first();

                $remaining = $newStock ? ($newStock->quantity - $newStock->dispensed) : 0;
                if (!$newStock || $remaining < $item['quantity']) {
                    throw new \Exception('الكمية غير كافية من الدواء (الرصيد المتاح: ' . $remaining . ')');
                }

                $dispensed->update($item);

                $newStock->increment('dispensed', $item['quantity']);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'تم تعديل الصرفية بنجاح'
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function destroy(DispensedMedicine $dispensedMedicine)
    {
        // Check if user owns this record
        if ($dispensedMedicine->user_id !== Auth::id()) {
            abort(403);
        }

        // Return quantity from dispensed to stock
        $monthStart = \Carbon\Carbon::parse($dispensedMedicine->dispense_date)->startOfMonth()->format('Y-m-d');
        $stock = \App\Models\Stock::where('user_id', Auth::id())
            ->where('medicine_id', $dispensedMedicine->medicine_id)
            ->where('stock_date', $monthStart)
            ->first();

        if ($stock) {
            $stock->decrement('dispensed', $dispensedMedicine->quantity);
        }

        $dispensedMedicine->delete();

        return redirect()->route('dispensed-medicines.index')->with('success', 'تم حذف السجل بنجاح');
    }

    /**
     * يتأكد من أن المستخدم قام بتحويل أرصدة إلى الشهر المطلوب (بوجود أي سجل رصيد لهذا الشهر).
     * إذا لم يوجد أي رصيد للشهر إطلاقاً فهذا يعني أن التحويل لم يتم.
     */
    private function ensureMonthTransferred(string $monthStart): void
    {
        $hasStock = \App\Models\Stock::where('user_id', Auth::id())
            ->where('stock_date', $monthStart)
            ->exists();

        if (!$hasStock) {
            throw new \Exception('لم يتم تحويل أرصدة للشهر الذي تريد الصرف منه');
        }
    }
}
