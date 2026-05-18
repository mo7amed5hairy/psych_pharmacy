<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('medicine_id')->constrained('medicines');
            $table->integer('quantity')->default(0);
            $table->date('stock_date'); // Single date field instead of month/year
            $table->timestamps();
            
            // Prevent duplicate stock entries
            $table->unique(['user_id', 'medicine_id', 'stock_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
