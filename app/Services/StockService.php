<?php

namespace App\Services;

use App\Models\DispensedMedicine;
use App\Models\InvoiceItem;
use App\Models\Stock;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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

    // ================= Manual balance entry =================

    public static function addRules(): array
    {
        return [
            'medicine_id' => 'required|exists:medicines,id',
            'quantity' => 'required|integer|min:0',
            'stock_date' => 'required|date',
        ];
    }

    public static function editRules(): array
    {
        return [
            'quantity' => 'required|integer|min:0',
            'dispensed' => 'required|integer|min:0',
        ];
    }

    public static function validateAddPayload(array $payload): array
    {
        return Validator::make($payload, self::addRules())->validate();
    }

    public static function validateEditPayload(array $payload): array
    {
        return Validator::make($payload, self::editRules())->validate();
    }

    /**
     * Adds to an existing balance or creates the balance row for the given date.
     */
    public static function addBalance(User $user, int $medicineId, int $quantity, string $stockDate): Stock
    {
        $stock = Stock::where('user_id', $user->id)
            ->where('medicine_id', $medicineId)
            ->where('stock_date', $stockDate)
            ->first();

        if ($stock) {
            $stock->quantity += $quantity;
            $stock->save();

            return $stock;
        }

        return Stock::create([
            'user_id' => $user->id,
            'medicine_id' => $medicineId,
            'stock_date' => $stockDate,
            'quantity' => $quantity,
            'dispensed' => 0,
        ]);
    }

    /**
     * @throws DomainException when dispensed exceeds the opening balance
     */
    public static function updateBalance(User $user, Stock $stock, int $quantity, int $dispensed): Stock
    {
        if ((int) $stock->user_id !== (int) $user->id) {
            throw new DomainException('غير مسموح بتعديل رصيد مستخدم آخر');
        }

        if ($dispensed > $quantity) {
            throw new DomainException("المنصرف لا يمكن أن يكون أكبر من رصيد أول الشهر (المتاح: {$quantity})");
        }

        $stock->quantity = $quantity;
        $stock->dispensed = $dispensed;
        $stock->save();

        return $stock;
    }

    // ================= Transfers =================

    public static function transferRules(): array
    {
        return [
            'from' => 'required|date',
            'to' => 'required|date',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2026|max:2050',
        ];
    }

    public static function validateTransferPayload(array $payload): array
    {
        return Validator::make($payload, self::transferRules())->validate();
    }

    public static function arabicMonth(int $month): string
    {
        $months = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'إبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ];

        return $months[$month] ?? (string) $month;
    }

    /**
     * Transfer the remaining balances of a period to the target month.
     *
     * @return array{count:int,qty:int,label:string}
     */
    public static function transfer(User $user, string $from, string $to, int $month, int $year): array
    {
        $fromMonth = Carbon::parse($from)->startOfMonth();
        $targetDate = Carbon::create($year, $month, 1);

        if ($targetDate->lte($fromMonth)) {
            throw new DomainException('الشهر المطلوب التحويل إليه يجب أن يبدأ بعد بداية شهر تاريخ (من)');
        }

        $rows = self::computeRows($user, $from, $to);

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

        return [
            'count' => $transferredCount,
            'qty' => $transferredQty,
            'label' => self::arabicMonth($month) . ' ' . $year,
        ];
    }

    /**
     * Build the transfer preview rows for a user and date range.
     */
    public static function computeRows(User $user, $from, $to): array
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
            $opening = $balance;
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
