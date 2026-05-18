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
        // Find existing record for this month
        $stock = Stock::where('user_id', $user->id)
            ->where('stock_date', $monthDate)
            ->first();

        // If it's already initialized, skip
        if ($stock && $stock->is_init) {
            return;
        }

        // Find the most recent previous month's stock records
        $lastStockDate = Stock::where('user_id', $user->id)
            ->where('stock_date', '<', $monthDate)
            ->max('stock_date');

        if (!$lastStockDate) {
            // No previous stock, but we should mark this month as "init" anyway
            if ($stock) {
                $stock->update(['is_init' => true]);
            }
            return;
        }

        // Get previous records
        $previousStocks = Stock::where('user_id', $user->id)
            ->where('stock_date', $lastStockDate)
            ->get();

        DB::transaction(function () use ($user, $monthDate, $previousStocks) {
            foreach ($previousStocks as $prevStock) {
                // Find or create current month record
                $currentStock = Stock::firstOrNew([
                    'user_id' => $user->id,
                    'medicine_id' => $prevStock->medicine_id,
                    'stock_date' => $monthDate,
                ]);

                if (!$currentStock->is_init) {
                    $oldQty = $prevStock->quantity;
                    // ADD previous balance to current (manual + auto)
                    $currentStock->quantity += $oldQty;
                    $currentStock->is_init = true;
                    $currentStock->save();

                    // Send Notification
                    $user->notify(new \App\Notifications\StockInitialized(
                        $prevStock->medicine->name,
                        $oldQty,
                        $currentStock->quantity,
                        \Carbon\Carbon::parse($currentStock->stock_date)->format('Y-m-d')
                    ));

                }

            }
        });
    }
}
