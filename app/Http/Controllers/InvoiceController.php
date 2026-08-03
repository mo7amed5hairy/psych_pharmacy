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
            ->get();

        return view('invoices.index', compact('invoices'));
    }



    public function create()
    {
        return view('invoices.create');
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'patient_name' => 'nullable|string|max:200',
            'referral_number' => 'required|string|max:50',
            'invoice_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'required|exists:medicines,id',
            'items.*.quantity' => 'required|integer|min:0',
        ]);

        // Check duplicate referral number for this user (same date only)
        $ref = $validated['referral_number'];
        $invoiceDate = !empty($validated['invoice_date']) ? $validated['invoice_date'] : now()->format('Y-m-d');
        $exists = \App\Models\Invoice::where('referral_number', $ref)
            ->where('user_id', Auth::id())
            ->whereDate('created_at', $invoiceDate)
            ->exists()
            || \App\Models\DispensedMedicine::where('referral_number', $ref)
                ->where('user_id', Auth::id())
                ->whereDate('dispense_date', $invoiceDate)
                ->exists();

        if ($exists) {
            $msg = "الرقم \"{$ref}\" مستخدم مسبقاً في هذا التاريخ (لمنع التكرار في نفس اليوم)";
            if ($request->expectsJson()) {
                return response()->json(['errors' => ['referral_number' => [$msg]]], 422);
            }
            return back()->withErrors(['referral_number' => $msg])->withInput();
        }

        DB::beginTransaction();
        try {
            $invoice = Invoice::create([
                'user_id' => Auth::id(),
                'patient_name' => $validated['patient_name'] ?? '-',
                'referral_number' => $validated['referral_number'],
            ]);

            if (!empty($validated['invoice_date'])) {
                $invoice->created_at = $validated['invoice_date'];
                $invoice->save();
            }

            foreach ($validated['items'] as $item) {
                if ($item['quantity'] == 0) continue;
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

    public function update(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'patient_name' => 'nullable|string|max:200',
            'referral_number' => 'required|string|max:50',
            'invoice_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'required|exists:medicines,id',
            'items.*.quantity' => 'required|integer|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Restore stock for old items
            foreach ($invoice->items as $oldItem) {
                $monthStart = now()->startOfMonth()->format('Y-m-d');
                $stock = \App\Models\Stock::where('user_id', Auth::id())
                    ->where('medicine_id', $oldItem->medicine_id)
                    ->where('stock_date', $monthStart)
                    ->lockForUpdate()
                    ->first();

                if ($stock) {
                    $stock->increment('quantity', $oldItem->quantity);
                }
            }

            // Delete old items
            $invoice->items()->delete();

            // Update invoice header
            $invoice->update([
                'patient_name' => $validated['patient_name'] ?? '-',
                'referral_number' => $validated['referral_number'],
            ]);

            // Update invoice date if provided
            if (!empty($validated['invoice_date'])) {
                $invoice->created_at = $validated['invoice_date'];
                $invoice->save();
            }

            // Create new items and deduct stock (skip items with 0 quantity)
            foreach ($validated['items'] as $item) {
                if ($item['quantity'] == 0) continue;

                $medicine = Medicine::find($item['medicine_id']);

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
                return response()->json(['message' => 'تم تحديث الفاتورة بنجاح', 'invoice' => $invoice->fresh(['items.medicine'])]);
            }
            return redirect()->route('invoices.show', $invoice)->with('success', 'تم تحديث الفاتورة بنجاح');
        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->expectsJson()) {
                return response()->json(['errors' => ['error' => [$e->getMessage()]]], 422);
            }
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
