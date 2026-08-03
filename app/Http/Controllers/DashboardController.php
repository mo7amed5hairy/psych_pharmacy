<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\Invoice;
use App\Models\Stock;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $today = Carbon::today();

        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        $stats = [
            'total_medicines' => Medicine::count(),
            'dispensed_today' => Invoice::whereDate('created_at', $today)
                ->where('user_id', $user->id)
                ->withCount('items')
                ->get()
                ->sum('items_count'),
            'monthly_stock' => Stock::where('user_id', $user->id)
                ->whereMonth('stock_date', $currentMonth)
                ->whereYear('stock_date', $currentYear)
                ->sum('quantity'),
            'low_stock' => Stock::where('user_id', $user->id)
                ->whereMonth('stock_date', $currentMonth)
                ->whereYear('stock_date', $currentYear)
                ->where('quantity', '<', 100)
                ->count(),
        ];

        $lastRun = \Illuminate\Support\Facades\Cache::store('file')->get('scheduler_heartbeat');
        $schedulerActive = $lastRun && \Carbon\Carbon::parse($lastRun)->gt(now()->subMinutes(5));

        return view('dashboard', compact('stats', 'user', 'schedulerActive', 'lastRun'));

    }
}
