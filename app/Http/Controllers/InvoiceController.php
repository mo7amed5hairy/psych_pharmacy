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

            // الشهر الذي سيتم الخصم منه = شهر تاريخ الفاتورة (وليس الشهر الحالي)
            $monthStart = \Carbon\Carbon::parse($invoiceDate)->startOfMonth()->format('Y-m-d');
            $this->ensureMonthTransferred($monthStart);

            foreach ($validated['items'] as $item) {
                if ($item['quantity'] == 0) continue;
                $medicine = Medicine::find($item['medicine_id']);

                // Deduct from stock of the invoice date's month
                $stock = \App\Models\Stock::where('user_id', Auth::id())
                    ->where('medicine_id', $item['medicine_id'])
                    ->where('stock_date', $monthStart)
                    ->lockForUpdate()
                    ->first();

                $remaining = $stock ? ($stock->quantity - $stock->dispensed) : 0;

                if (!$stock || $remaining < $item['quantity']) {
                    throw new \Exception("الكمية غير كافية من {$medicine->name} (الرصيد المتاح: " . $remaining . ")");
                }

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'medicine_id' => $item['medicine_id'],
                    'quantity' => $item['quantity'],
                    'price_at_dispense' => $medicine->price_hotline,
                ]);

                $stock->increment('dispensed', $item['quantity']);
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

        // شهري الخصم: القديم من تاريخ الفاتورة الحالي، والجديد من تاريخ الفاتورة المُرسَل
        $oldMonthStart = $invoice->created_at
            ? \Carbon\Carbon::parse($invoice->created_at)->startOfMonth()->format('Y-m-d')
            : now()->startOfMonth()->format('Y-m-d');
        $newMonthStart = !empty($validated['invoice_date'])
            ? \Carbon\Carbon::parse($validated['invoice_date'])->startOfMonth()->format('Y-m-d')
            : $oldMonthStart;

        DB::beginTransaction();
        try {
            // Restore stock for old items (from the OLD invoice date's month)
            foreach ($invoice->items as $oldItem) {
                $stock = \App\Models\Stock::where('user_id', Auth::id())
                    ->where('medicine_id', $oldItem->medicine_id)
                    ->where('stock_date', $oldMonthStart)
                    ->lockForUpdate()
                    ->first();

                if ($stock) {
                    $stock->decrement('dispensed', $oldItem->quantity);
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

            // Check the new month has a transferred balance before deducting
            $this->ensureMonthTransferred($newMonthStart);

            // Create new items and deduct stock (skip items with 0 quantity)
            foreach ($validated['items'] as $item) {
                if ($item['quantity'] == 0) continue;

                $medicine = Medicine::find($item['medicine_id']);

                $stock = \App\Models\Stock::where('user_id', Auth::id())
                    ->where('medicine_id', $item['medicine_id'])
                    ->where('stock_date', $newMonthStart)
                    ->lockForUpdate()
                    ->first();

                $remaining = $stock ? ($stock->quantity - $stock->dispensed) : 0;

                if (!$stock || $remaining < $item['quantity']) {
                    throw new \Exception("الكمية غير كافية من {$medicine->name} (الرصيد المتاح: " . $remaining . ")");
                }

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'medicine_id' => $item['medicine_id'],
                    'quantity' => $item['quantity'],
                    'price_at_dispense' => $medicine->price_hotline,
                ]);

                $stock->increment('dispensed', $item['quantity']);
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
