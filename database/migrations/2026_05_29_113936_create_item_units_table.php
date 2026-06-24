<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('item_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();

            // معامل التحويل بالنسبة للوحدة الصغرى (مثال: الكرتون يحتوي 24 حبة)
            $table->decimal('conversion_factor', 12, 4)->default(1.0000);

            // التكلفة وسعر البيع الافتراضي لهذه الوحدة بالتحديد
            $table->decimal('cost', 15, 4)->default(0.0000);
            $table->decimal('price', 15, 4)->default(0.0000);

            $table->timestamps();
            $table->softDeletes(); // التزاماً بالمعمارية الصارمة للنظام
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_units');
    }
};
