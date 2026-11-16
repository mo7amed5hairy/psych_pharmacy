<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\Stock;
use App\Services\StockService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $stockDate = $request->get('stock_date', now()->format('Y-m-01'));

        // Extract month and year from date for display purposes
        $date = \Carbon\Carbon::createFromFormat('Y-m-d', $stockDate);
        $month = $date->month;
        $year = $date->year;

        $stock = Stock::with('medicine.unitType')
            ->where('user_id', $user->id)
            ->where('stock_date', 'like', $date->format('Y-m') . '%')
            ->get();

        return view('stock.index', compact('stock', 'month', 'year', 'stockDate'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate(StockService::addRules());

        StockService::addBalance(
            Auth::user(),
            (int) $validated['medicine_id'],
            (int) $validated['quantity'],
            (string) $validated['stock_date']
        );

        return redirect()
            ->route('stock.index', ['stock_date' => $validated['stock_date']])
            ->with('success', 'تم إضافة الرصيد بنجاح');
    }

    public function update(Request $request, Stock $stock)
    {
        $validated = $request->validate(StockService::editRules());

        if ($stock->user_id !== Auth::id()) {
            abort(403);
        }

        try {
            StockService::updateBalance(
                Auth::user(),
                $stock,
                (int) $validated['quantity'],
                (int) $validated['dispensed']
            );
        } catch (DomainException $e) {
            return redirect()->route('stock.index')
                ->withErrors(['error' => $e->getMessage()])
                ->withInput();
        }

        return redirect()->route('stock.index')->with('success', 'تم تحديث الرصيد بنجاح');
    }
}
