<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispensed_medicines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('medicine_id')->constrained('medicines');
            $table->string('referral_number')->nullable();
            $table->date('dispense_date');
            $table->integer('quantity')->default(0);
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['user_id', 'dispense_date']);
            $table->index(['medicine_id', 'dispense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispensed_medicines');
    }
};
