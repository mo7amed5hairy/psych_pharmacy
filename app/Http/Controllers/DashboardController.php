<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\Invoice;
use App\Models\Stock;
use App\Services\MaintenanceService;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function __construct(private MaintenanceService $maintenance)
    {
    }

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
                ->get()
                ->sum(fn($s) => $s->quantity - $s->dispensed),
            'low_stock' => Stock::where('user_id', $user->id)
                ->whereMonth('stock_date', $currentMonth)
                ->whereYear('stock_date', $currentYear)
                ->get()
                ->filter(fn($s) => ($s->quantity - $s->dispensed) < 100)
                ->count(),
        ];

        $stockAutoTransfer = [
            'enabled' => $this->maintenance->transferEnabled(),
            'nextDueLabel' => $this->dueLabel($this->maintenance->nextTransferDue()),
            'lastRun' => $this->maintenance->lastTransferAt(),
        ];

        return view('dashboard', compact('stats', 'user', 'stockAutoTransfer'));

    }

    private function dueLabel(?string $ym): string
    {
        if (!$ym) {
            return 'غير محدد';
        }

        try {
            return Carbon::createFromFormat('Y-m', $ym)
                ->locale('ar')
                ->translatedFormat('F Y');
        } catch (\Throwable) {
            return $ym;
        }
    }
}
