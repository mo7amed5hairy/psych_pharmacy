<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->integer('dispensed')->default(0)->after('quantity');
        });

        // Backfill: existing rows store REMAINING in quantity.
        // Convert to opening: opening = old_quantity + dispensed(for the month)
        // and set dispensed = dispensed(from dispensed-medicines + invoice items).
        $stocks = DB::table('stocks')->get();

        foreach ($stocks as $stock) {
            $month = (int) date('n', strtotime($stock->stock_date));
            $year = (int) date('Y', strtotime($stock->stock_date));

            $dm = DB::table('dispensed_medicines')
                ->where('user_id', $stock->user_id)
                ->where('medicine_id', $stock->medicine_id)
                ->whereYear('dispense_date', $year)
                ->whereMonth('dispense_date', $month)
                ->sum('quantity');

            $inv = DB::table('invoice_items')
                ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
                ->where('invoices.user_id', $stock->user_id)
                ->where('invoice_items.medicine_id', $stock->medicine_id)
                ->whereYear('invoices.created_at', $year)
                ->whereMonth('invoices.created_at', $month)
                ->sum('invoice_items.quantity');

            $dispensedTotal = (int) $dm + (int) $inv;

            DB::table('stocks')
                ->where('id', $stock->id)
                ->update([
                    'quantity' => $stock->quantity + $dispensedTotal,
                    'dispensed' => $dispensedTotal,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn('dispensed');
        });
    }
};
