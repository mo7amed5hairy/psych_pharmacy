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
        $userId = auth()->id(); // Enforce current user

        $dmData = DispensedMedicine::select(
            'medicine_id',
            'referral_number',
            DB::raw('SUM(quantity) as total_quantity')
        )
            ->whereMonth('dispense_date', $month)
            ->whereYear('dispense_date', $year)
            ->where('user_id', $userId)
            ->groupBy('medicine_id', 'referral_number')
            ->get();

        $invData = InvoiceItem::select(
            'invoice_items.medicine_id',
            'invoices.referral_number',
            DB::raw('SUM(invoice_items.quantity) as total_quantity')
        )
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->whereMonth('invoices.created_at', $month)
            ->whereYear('invoices.created_at', $year)
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

        return view('reports.monthly', compact('pivot', 'medicines', 'referralNumbers', 'month', 'year', 'userId'));
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
            ->map(function ($stock) use ($fromDate, $toDate) {
                $dmQuery = DispensedMedicine::where('medicine_id', $stock->medicine_id)
                    ->where('user_id', $stock->user_id);
                $invQuery = InvoiceItem::where('medicine_id', $stock->medicine_id)
                    ->whereHas('invoice', function ($q) use ($stock) {
                        $q->where('user_id', $stock->user_id);
                    });

                if ($fromDate) {
                    $dmQuery->where('dispense_date', '>=', $fromDate);
                    $invQuery->whereHas('invoice', fn($q) => $q->where('created_at', '>=', $fromDate));
                }
                if ($toDate) {
                    $dmQuery->where('dispense_date', '<=', $toDate);
                    $invQuery->whereHas('invoice', fn($q) => $q->where('created_at', '<=', $toDate . ' 23:59:59'));
                }

                $fromDispensed = $dmQuery->sum('quantity');
                $fromInvoices = $invQuery->sum('quantity');

                $dispensed = $fromDispensed + $fromInvoices;
                $opening = $stock->quantity + $dispensed;
                $stock->dispensed = $dispensed;
                $stock->opening = $opening;
                $stock->remaining = $opening - $dispensed;
                return $stock;
            });

        $totals = [
            'total_stock' => $inventory->sum('opening'),
            'total_dispensed' => $inventory->sum('dispensed'),
            'total_remaining' => $inventory->sum('remaining'),
        ];

        return view('reports.inventory', compact('inventory', 'totals', 'userId', 'fromDate', 'toDate'));
    }
}
