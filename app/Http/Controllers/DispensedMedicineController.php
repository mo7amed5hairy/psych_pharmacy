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

        // Check duplicates BEFORE transaction
        $usedRefs = [];
        foreach ($validated['medicines'] as $item) {
            $ref = $item['referral_number'] ?? null;
            if (!$ref)
                continue;
            if (in_array($ref, $usedRefs)) {
                return response()->json(['status' => 'error', 'message' => "الرقم \"{$ref}\" مكرر في نفس الطلب"], 422);
            }
            $usedRefs[] = $ref;
            $exists = \App\Models\Invoice::where('referral_number', $ref)->where('user_id', Auth::id())->exists()
                || \App\Models\DispensedMedicine::where('referral_number', $ref)->where('user_id', Auth::id())->exists();
            if ($exists) {
                $maxInv = (int) \App\Models\Invoice::where('user_id', Auth::id())->selectRaw('MAX(CAST(referral_number AS UNSIGNED)) as m')->value('m');
                $maxDM = (int) \App\Models\DispensedMedicine::where('user_id', Auth::id())->selectRaw('MAX(CAST(referral_number AS UNSIGNED)) as m')->value('m');
                $next = max($maxInv, $maxDM) + 1;
                return response()->json(['status' => 'error', 'message' => "الرقم \"{$ref}\" مستخدم من قبل — الرقم التالي المتاح: {$next}"], 422);
            }
        }

        DB::beginTransaction();

        try {

            /**
             * 1) دمج التكرار (same medicine + same date)
             */
            $merged = [];

            foreach ($validated['medicines'] as $item) {

                $key = $item['medicine_id'] . '_' . $item['dispense_date'];

                if (!isset($merged[$key])) {

                    $merged[$key] = $item;

                } else {

                    // دمج الكميات + الحفاظ على أول referral لو موجود
                    $merged[$key]['quantity'] += $item['quantity'];

                    if (empty($merged[$key]['referral_number']) && !empty($item['referral_number'])) {
                        $merged[$key]['referral_number'] = $item['referral_number'];
                    }
                }
            }

            $successCount = 0;

            foreach ($merged as $item) {

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
                $stock = \App\Models\Stock::where('user_id', Auth::id())
                    ->where('medicine_id', $item['medicine_id'])
                    ->where('stock_date', $monthStart)
                    ->lockForUpdate()
                    ->first();


                if (!$stock) {
                    throw new \Exception("الصنف {$medicine->name} غير موجود في المخزون");
                }

                /**
                 * 4) التحقق من الكمية
                 */
                if ($stock->quantity < $item['quantity']) {
                    throw new \Exception(
                        "الكمية غير كافية من {$medicine->name} (المتاح {$stock->quantity})"
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
                 * 6) خصم من المخزون
                 */
                $stock->decrement('quantity', $item['quantity']);

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

            // Check duplicate (exclude current record)
            $ref = $item['referral_number'] ?? null;
            if ($ref) {
                $exists = \App\Models\Invoice::where('referral_number', $ref)->where('user_id', Auth::id())->exists()
                    || \App\Models\DispensedMedicine::where('referral_number', $ref)->where('user_id', Auth::id())->where('id', '!=', $id)->exists();
                if ($exists) {
                    $maxInv = (int) \App\Models\Invoice::where('user_id', Auth::id())->selectRaw('MAX(CAST(referral_number AS UNSIGNED)) as m')->value('m');
                    $maxDM = (int) \App\Models\DispensedMedicine::where('user_id', Auth::id())->selectRaw('MAX(CAST(referral_number AS UNSIGNED)) as m')->value('m');
                    $next = max($maxInv, $maxDM) + 1;
                    return response()->json(['status' => 'error', 'message' => "الرقم \"{$ref}\" مستخدم من قبل — الرقم التالي المتاح: {$next}"], 422);
                }
            }

            DB::transaction(function () use ($dispensed, $item) {

                $oldStock = \App\Models\Stock::where('user_id', Auth::id())
                    ->where('medicine_id', $dispensed->medicine_id)
                    ->first();

                if ($oldStock) {
                    $oldStock->increment('quantity', $dispensed->quantity);
                }

                $newStock = \App\Models\Stock::where('user_id', Auth::id())
                    ->where('medicine_id', $item['medicine_id'])
                    ->first();

                if (!$newStock || $newStock->quantity < $item['quantity']) {
                    throw new \Exception('الكمية غير متاحة في المخزن');
                }

                $dispensed->update($item);

                $newStock->decrement('quantity', $item['quantity']);
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

        // Return quantity to stock
        $stock = \App\Models\Stock::where('user_id', Auth::id())
            ->where('medicine_id', $dispensedMedicine->medicine_id)
            ->where('stock_date', $dispensedMedicine->dispense_date)
            ->first();

        if ($stock) {
            $stock->increment('quantity', $dispensedMedicine->quantity);
        }

        $dispensedMedicine->delete();

        return redirect()->route('dispensed-medicines.index')->with('success', 'تم حذف السجل بنجاح');
    }
}
