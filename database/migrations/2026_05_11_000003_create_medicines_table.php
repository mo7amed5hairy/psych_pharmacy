<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->foreignId('unit_type_id')->constrained('unit_types');
            $table->decimal('price_hotline', 10, 2)->default(0);
            $table->decimal('price_contract', 10, 2)->default(0);
            $table->decimal('price_clinic', 10, 2)->default(0);
            $table->timestamps();
            
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicines');
    }
};
