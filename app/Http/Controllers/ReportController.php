<?php

namespace App\Http\Controllers;

use App\Models\DispensedMedicine;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Medicine;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function monthly(Request $request)
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);
        $userId = auth()->id();

        $dmData = DispensedMedicine::select(
            'medicine_id',
            'dispense_date',
            DB::raw('SUM(quantity) as total_quantity')
        )
            ->whereMonth('dispense_date', $month)
            ->whereYear('dispense_date', $year)
            ->where('user_id', $userId)
            ->groupBy('medicine_id', 'dispense_date')
            ->get();

        $invData = InvoiceItem::select(
            'invoice_items.medicine_id',
            DB::raw('DATE(invoices.created_at) as dispense_date'),
            DB::raw('SUM(invoice_items.quantity) as total_quantity')
        )
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->whereMonth('invoices.created_at', $month)
            ->whereYear('invoices.created_at', $year)
            ->where('invoices.user_id', $userId)
            ->groupBy('invoice_items.medicine_id', DB::raw('DATE(invoices.created_at)'))
            ->get();

        $allData = $dmData->concat($invData);

        $arabicDayNames = [
            'Saturday' => 'السبت',
            'Sunday' => 'الأحد',
            'Monday' => 'الإثنين',
            'Tuesday' => 'الثلاثاء',
            'Wednesday' => 'الأربعاء',
            'Thursday' => 'الخميس',
            'Friday' => 'الجمعة',
        ];

        $dates = $allData->pluck('dispense_date')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->map(function ($date) use ($arabicDayNames) {
                $dateStr = $date instanceof \Carbon\Carbon ? $date->format('Y-m-d') : $date;
                $dayName = $arabicDayNames[\Carbon\Carbon::parse($dateStr)->format('l')] ?? '';
                return (object) [
                    'date' => $dateStr,
                    'label' => $dayName,
                ];
            });

        $medicineIds = $allData->pluck('medicine_id')->unique();
        $medicines = Medicine::whereIn('id', $medicineIds)->get()->keyBy('id');

        $pivot = [];
        foreach ($allData as $item) {
            $medId = $item->medicine_id;
            $rawDate = $item->dispense_date;
            $date = $rawDate instanceof \Carbon\Carbon ? $rawDate->format('Y-m-d') : ($rawDate ?? '_none');
            $pivot[$medId][$date] = ($pivot[$medId][$date] ?? 0) + $item->total_quantity;
        }

        return view('reports.monthly', compact('pivot', 'medicines', 'dates', 'month', 'year', 'userId'));
    }


    public function daily(Request $request)
    {
        $date = $request->get('date', now()->format('Y-m-d'));
        $userId = auth()->id();

        $dmData = DispensedMedicine::select(
            'medicine_id',
            'referral_number',
            DB::raw('SUM(quantity) as total_quantity')
        )
            ->whereDate('dispense_date', $date)
            ->where('user_id', $userId)
            ->groupBy('medicine_id', 'referral_number')
            ->get();

        $invData = InvoiceItem::select(
            'invoice_items.medicine_id',
            'invoices.referral_number',
            DB::raw('SUM(invoice_items.quantity) as total_quantity')
        )
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->whereDate('invoices.created_at', $date)
            ->where('invoices.user_id', $userId)
            ->groupBy('invoice_items.medicine_id', 'invoices.referral_number')
            ->get();

        $allData = $dmData->concat($invData);

        $referralNumbers = $allData->pluck('referral_number')
            ->filter()
            ->unique()
            ->sort(function ($a, $b) {
                return (int) $a - (int) $b;
            })
            ->values();

        $medicineIds = $allData->pluck('medicine_id')->unique();
        $medicines = Medicine::whereIn('id', $medicineIds)->get()->keyBy('id');

        $pivot = [];
        foreach ($allData as $item) {
            $medId = $item->medicine_id;
            $refNum = $item->referral_number ?? '_none';
            $pivot[$medId][$refNum] = ($pivot[$medId][$refNum] ?? 0) + $item->total_quantity;
        }

        return view('reports.daily', compact('pivot', 'medicines', 'referralNumbers', 'date', 'userId'));
    }


    public function clinic(Request $request)
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);
        $userId = auth()->id();

        $dmData = DispensedMedicine::select(
            'medicine_id',
            'dispense_date',
            'referral_number',
            DB::raw('SUM(quantity) as total_quantity')
        )
            ->whereMonth('dispense_date', $month)
            ->whereYear('dispense_date', $year)
            ->where('user_id', $userId)
            ->groupBy('medicine_id', 'dispense_date', 'referral_number')
            ->get();

        $invData = InvoiceItem::select(
            'invoice_items.medicine_id',
            DB::raw('DATE(invoices.created_at) as dispense_date'),
            'invoices.referral_number',
            DB::raw('SUM(invoice_items.quantity) as total_quantity')
        )
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->whereMonth('invoices.created_at', $month)
            ->whereYear('invoices.created_at', $year)
            ->where('invoices.user_id', $userId)
            ->groupBy('invoice_items.medicine_id', DB::raw('DATE(invoices.created_at)'), 'invoices.referral_number')
            ->get();

        $allData = $dmData->concat($invData);

        // Group by medicine to get total quantities
        $medicineQuantities = [];
        $dates = collect();
        $referralNumbers = collect();

        foreach ($allData as $item) {
            $medId = $item->medicine_id;
            $medicineQuantities[$medId] = ($medicineQuantities[$medId] ?? 0) + $item->total_quantity;
            if ($item->dispense_date) {
                $dates->push($item->dispense_date);
            }
            if ($item->referral_number) {
                $referralNumbers->push($item->referral_number);
            }
        }

        $medicineIds = array_keys($medicineQuantities);
        $medicines = Medicine::with('unitType')->whereIn('id', $medicineIds)->get();

        $reportRows = [];
        $grandTotal = 0;

        foreach ($medicines as $med) {
            $qty = $medicineQuantities[$med->id] ?? 0;
            $unitPrice = (float) ($med->price_clinic ?? 0);
            $total = $qty * $unitPrice;
            $grandTotal += $total;
            $reportRows[] = (object) [
                'name' => $med->name,
                'unit' => $med->unitType->name ?? '',
                'unit_price' => $unitPrice,
                'quantity' => $qty,
                'total' => $total,
            ];
        }

        $daysCount = $dates->unique()->count();
        $ticketsCount = $referralNumbers->unique()->count();

        return view('reports.clinic', compact('reportRows', 'grandTotal', 'daysCount', 'ticketsCount', 'month', 'year', 'userId'));
    }


    public function inventory(Request $request)
    {
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $userId = auth()->id(); // Enforce current user
        $medicineId = $request->get('medicine_id');

        if (!$fromDate)
            $fromDate = now()->startOfMonth()->format('Y-m-d');
        if (!$toDate)
            $toDate = now()->format('Y-m-d');

        $query = Stock::with(['medicine.unitType', 'user'])
            ->where('user_id', $userId);

        if ($medicineId) {
            $query->where('medicine_id', $medicineId);
        }

        if ($fromDate) {
            $query->where('stock_date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('stock_date', '<=', $toDate);
        }

        $inventory = $query->get()
            ->groupBy('medicine_id')
            ->map(function ($stocks) use ($fromDate, $toDate) {
                // Take the first stock record as a base to keep medicine/user relations
                $stock = $stocks->first();
                // Sum the quantities of all stock records in this group
                $stock->quantity = $stocks->sum('quantity');

                $dmQuery = DispensedMedicine::where('medicine_id', $stock->medicine_id)
                    ->where('user_id', $stock->user_id);
                $invQuery = InvoiceItem::where('medicine_id', $stock->medicine_id)
                    ->whereHas('invoice', function ($q) use ($stock) {
                        $q->where('user_id', $stock->user_id);
                    });

                if ($fromDate) {
                    $dmQuery->where('dispense_date', '>=', $fromDate);
                    $invQuery->whereHas('invoice', fn($q) => $q->whereDate('created_at', '>=', $fromDate));
                }
                if ($toDate) {
                    $dmQuery->where('dispense_date', '<=', $toDate);
                    $invQuery->whereHas('invoice', fn($q) => $q->whereDate('created_at', '<=', $toDate));
                }

                $fromDispensed = (int) $dmQuery->sum('quantity');
                $fromInvoices = (int) $invQuery->sum('quantity');

                $dispensed = $fromDispensed + $fromInvoices;
                $opening = $stock->quantity + $dispensed;
                $stock->dispensed = $dispensed;
                $stock->opening = $opening;
                $stock->remaining = $opening - $dispensed;
                return $stock;
            })->values();

        $totals = [
            'total_stock' => $inventory->sum('opening'),
            'total_dispensed' => $inventory->sum('dispensed'),
            'total_remaining' => $inventory->sum('remaining'),
        ];

        return view('reports.inventory', compact('inventory', 'totals', 'userId', 'fromDate', 'toDate'));
    }
}
