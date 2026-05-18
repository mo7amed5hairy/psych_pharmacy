<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->timestamps();
        });

        // Insert default data
        DB::table('unit_types')->insert([
            ['name' => 'قرص', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'كبسولة', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'أمبول', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'كيس', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'زجاجة', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'تيوب', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'قطرة', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_types');
    }
};
