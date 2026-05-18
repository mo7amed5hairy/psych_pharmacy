<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\Medicine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        \App\Services\StockService::initializeMonthlyStock($user);

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
        $validated = $request->validate([
            'medicine_id' => 'required|exists:medicines,id',
            'quantity' => 'required|integer|min:0',
            'stock_date' => 'required|date',
        ]);

        $stock = Stock::firstOrCreate(
            [
                'user_id' => Auth::id(),
                'medicine_id' => $validated['medicine_id'],
                'stock_date' => $validated['stock_date'],
            ],
            [
                'quantity' => $validated['quantity'],
            ]
        );

        // If stock already exists, update quantity
        if (!$stock->wasRecentlyCreated) {
            $stock->update(['quantity' => $validated['quantity']]);
        }

        return redirect()->route('stock.index', ['stock_date' => $validated['stock_date']])->with('success', 'تم إضافة الرصيد بنجاح');
    }

    public function update(Request $request, Stock $stock)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0',
        ]);

        $stock->update($validated);

        return redirect()->route('stock.index')->with('success', 'تم تحديث الرصيد بنجاح');
    }
}
