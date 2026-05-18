<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Medicine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function index()
    {
        $invoices = Invoice::with(['items.medicine'])
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('invoices.index', compact('invoices'));
    }

    public function create()
    {
        \App\Services\StockService::initializeMonthlyStock(Auth::user());
        return view('invoices.create');
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'patient_name' => 'required|string|max:200',
            'referral_number' => 'required|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'required|exists:medicines,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        // Check duplicate referral number for this user
        $ref = $validated['referral_number'];
        $exists = \App\Models\Invoice::where('referral_number', $ref)->where('user_id', Auth::id())->exists()
            || \App\Models\DispensedMedicine::where('referral_number', $ref)->where('user_id', Auth::id())->exists();
        if ($exists) {
            $maxInv = (int) \App\Models\Invoice::where('user_id', Auth::id())->selectRaw('MAX(CAST(referral_number AS UNSIGNED)) as m')->value('m');
            $maxDM = (int) \App\Models\DispensedMedicine::where('user_id', Auth::id())->selectRaw('MAX(CAST(referral_number AS UNSIGNED)) as m')->value('m');
            $next = max($maxInv, $maxDM) + 1;

            $msg = "الرقم \"{$ref}\" مستخدم من قبل — الرقم التالي المتاح: {$next}";
            if ($request->expectsJson()) {
                return response()->json(['errors' => ['referral_number' => [$msg]]], 422);
            }
            return back()->withErrors(['referral_number' => $msg])->withInput();
        }

        DB::beginTransaction();
        try {
            $invoice = Invoice::create([
                'user_id' => Auth::id(),
                'patient_name' => $validated['patient_name'],
                'referral_number' => $validated['referral_number'],
            ]);

            foreach ($validated['items'] as $item) {
                $medicine = Medicine::find($item['medicine_id']);

                // Deduct from stock
                $monthStart = now()->startOfMonth()->format('Y-m-d');
                $stock = \App\Models\Stock::where('user_id', Auth::id())
                    ->where('medicine_id', $item['medicine_id'])
                    ->where('stock_date', $monthStart)
                    ->lockForUpdate()
                    ->first();

                if (!$stock || $stock->quantity < $item['quantity']) {
                    throw new \Exception("الكمية غير كافية من {$medicine->name} (الرصيد المتاح: " . ($stock ? $stock->quantity : 0) . ")");
                }

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'medicine_id' => $item['medicine_id'],
                    'quantity' => $item['quantity'],
                    'price_at_dispense' => $medicine->price_hotline,
                ]);

                $stock->decrement('quantity', $item['quantity']);
            }

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'تم إنشاء الفاتورة بنجاح', 'redirect' => route('invoices.show', $invoice)]);
            }
            return redirect()->route('invoices.show', $invoice)->with('success', 'تم إنشاء الفاتورة بنجاح');
        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->expectsJson()) {
                return response()->json(['errors' => ['error' => [$e->getMessage()]]], 422);
            }
            return back()->withErrors(['error' => $e->getMessage()]);
        }

    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['items.medicine.unitType', 'user']);
        return view('invoices.show', compact('invoice'));
    }

    public function print(Invoice $invoice)
    {
        $invoice->load(['items.medicine.unitType', 'user']);
        return view('invoices.print', compact('invoice'));
    }
}
