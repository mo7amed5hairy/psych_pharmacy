<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Initializes the specified or current month's stock by carrying over the balance
     * from the previous month if it doesn't already exist.
     */
    public static function initializeMonthlyStock(User $user, $date = null)
    {
        $targetDate = $date ? Carbon::parse($date)->startOfMonth() : Carbon::now()->startOfMonth();
        self::initUserForMonth($user, $targetDate->format('Y-m-d'));
    }

    /**
     * Initializes monthly stock for ALL users for a specific or current month.
     */
    public static function initializeAllUsersMonthlyStock($date = null)
    {
        $targetDate = $date ? Carbon::parse($date)->startOfMonth() : Carbon::now()->startOfMonth();
        $users = User::all();

        foreach ($users as $user) {
            self::initUserForMonth($user, $targetDate->format('Y-m-d'));
        }
    }

    /**
     * Internal helper to initialize a specific month for a user.
     */
    private static function initUserForMonth(User $user, $monthDate)
    {
        // Find the most recent previous month's stock records
        $lastStockDate = Stock::where('user_id', $user->id)
            ->where('stock_date', '<', $monthDate)
            ->max('stock_date');

        if (!$lastStockDate) {
            return;
        }

        // Get all previous records
        $previousStocks = Stock::where('user_id', $user->id)
            ->where('stock_date', $lastStockDate)
            ->get();

        if ($previousStocks->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($user, $monthDate, $previousStocks) {
            foreach ($previousStocks as $prevStock) {
                // Find or create current month record
                $currentStock = Stock::firstOrNew([
                    'user_id' => $user->id,
                    'medicine_id' => $prevStock->medicine_id,
                    'stock_date' => $monthDate,
                ]);

                // Skip only if this medicine is already initialized for this month
                if ($currentStock->exists && $currentStock->is_init) {
                    continue;
                }

                $oldQty = $prevStock->quantity;
                $currentStock->quantity += $oldQty;
                $currentStock->is_init = true;
                $currentStock->save();

                // Send notification
                $user->notify(new \App\Notifications\StockInitialized(
                    $prevStock->medicine->name,
                    $oldQty,
                    $currentStock->quantity,
                    \Carbon\Carbon::parse($currentStock->stock_date)->format('Y-m-d')
                ));
            }
        });
    }
}
