<?php

namespace App\Http\Controllers;

use App\Models\DispensedMedicine;
use App\Models\InvoiceItem;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StockTransferController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->get('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', now()->format('Y-m-d'));

        $rows = $this->computeRows(Auth::user(), $from, $to);

        $months = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'إبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ];
        $years = range(2026, 2050);
        $targetMonth = now()->month;
        $targetYear = now()->year;

        return view('stock-transfer.index', compact('rows', 'from', 'to', 'months', 'years', 'targetMonth', 'targetYear'));
    }

    public function filter(Request $request)
    {
        $from = $request->get('from');
        $to = $request->get('to');

        if (!$from || !$to) {
            return response()->json([]);
        }

        return response()->json($this->computeRows(Auth::user(), $from, $to));
    }

    public function transfer(Request $request)
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2026|max:2050',
        ]);

        $user = Auth::user();
        $fromDate = Carbon::parse($validated['from']);
        $targetDate = Carbon::create((int) $validated['year'], (int) $validated['month'], 1);

        if ($targetDate->lte($fromDate->startOfMonth())) {
            return back()->withErrors(['error' => 'الشهر المطلوب التحويل إليه يجب أن يبدأ بعد بداية شهر تاريخ (من)']);
        }

        $rows = $this->computeRows($user, $validated['from'], $validated['to']);

        $targetDateStr = $targetDate->format('Y-m-d');
        $transferredCount = 0;
        $transferredQty = 0;

        DB::transaction(function () use ($user, $rows, $targetDateStr, &$transferredCount, &$transferredQty) {
            foreach ($rows as $row) {
                $remaining = (int) $row['remaining'];

                $stock = Stock::where('user_id', $user->id)
                    ->where('medicine_id', $row['medicine_id'])
                    ->where('stock_date', $targetDateStr)
                    ->first();

                if ($stock) {
                    // Override (replace) the existing balance of the target month
                    $stock->quantity = $remaining;
                    $stock->is_init = true;
                    $stock->save();
                } elseif ($remaining > 0) {
                    Stock::create([
                        'user_id' => $user->id,
                        'medicine_id' => $row['medicine_id'],
                        'quantity' => $remaining,
                        'stock_date' => $targetDateStr,
                        'is_init' => true,
                    ]);
                }

                if ($remaining > 0) {
                    $transferredCount++;
                    $transferredQty += $remaining;
                }
            }
        });

        $targetLabel = $this->arabicMonth((int) $validated['month']) . ' ' . $validated['year'];

        return redirect()->route('stock-transfer.index', [
            'from' => $validated['from'],
            'to' => $validated['to'],
        ])->with('success', "تم تحويل أرصدة {$transferredCount} صنف (إجمالي {$transferredQty}) إلى شهر {$targetLabel} بنجاح (تم استبدال الرصيد القديم)");
    }

    private function arabicMonth(int $month): string
    {
        $months = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'إبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ];
        return $months[$month] ?? (string) $month;
    }

    private function computeRows(User $user, $from, $to): array
    {
        $fromDate = Carbon::parse($from)->format('Y-m-d');
        $toDate = Carbon::parse($to)->format('Y-m-d');
        $fromMonthStart = Carbon::parse($from)->startOfMonth()->format('Y-m-d');

        $stocks = Stock::with('medicine.unitType')
            ->where('user_id', $user->id)
            ->whereBetween('stock_date', [$fromDate, $toDate])
            ->orderBy('stock_date')
            ->get()
            ->groupBy('medicine_id');

        if ($stocks->isEmpty()) {
            return [];
        }

        $dmGrouped = DispensedMedicine::select('medicine_id', DB::raw('SUM(quantity) as total'))
            ->where('user_id', $user->id)
            ->whereBetween('dispense_date', [$fromDate, $toDate])
            ->groupBy('medicine_id')
            ->pluck('total', 'medicine_id');

        $invGrouped = InvoiceItem::select('invoice_items.medicine_id', DB::raw('SUM(invoice_items.quantity) as total'))
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.user_id', $user->id)
            ->whereBetween(DB::raw('DATE(invoices.created_at)'), [$fromDate, $toDate])
            ->groupBy('invoice_items.medicine_id')
            ->pluck('total', 'medicine_id');

        $rows = [];
        foreach ($stocks as $medicineId => $records) {
            $first = $records->first();

            $openingRecord = null;
            foreach ($records as $record) {
                if ($record->stock_date->format('Y-m-d') === $fromMonthStart) {
                    $openingRecord = $record;
                    break;
                }
            }
            if (!$openingRecord) {
                $openingRecord = $first;
            }

            $balance = (int) $openingRecord->quantity;
            $dispensed = (int) ($dmGrouped[$medicineId] ?? 0) + (int) ($invGrouped[$medicineId] ?? 0);
            $opening = $balance + $dispensed;
            $remaining = $opening - $dispensed;

            $rows[] = [
                'medicine_id' => $medicineId,
                'name' => $first->medicine->name ?? '',
                'unit' => $first->medicine->unitType->name ?? '',
                'opening' => $opening,
                'dispensed' => $dispensed,
                'remaining' => $remaining,
            ];
        }

        return $rows;
    }
}
