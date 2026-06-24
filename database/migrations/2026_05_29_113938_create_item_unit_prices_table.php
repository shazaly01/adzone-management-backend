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
        Schema::create('item_unit_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();

            // ربط السعر بالوحدة المحددة للصنف
            $table->foreignId('item_unit_id')->constrained('item_units')->cascadeOnDelete();

            // ربط السعر بفئة قائمة الأسعار المعنية (جمهور، جملة...)
            $table->foreignId('price_list_id')->constrained('price_lists')->cascadeOnDelete();

            // نسبة الخصم المشتقة من السعر الأساسي وقيمة السعر الفعلي النهائي
            $table->decimal('discount_percentage', 5, 2)->default(0.00);
            $table->decimal('price', 15, 4)->default(0.0000);

            $table->timestamps();
            $table->softDeletes(); // التزاماً بالبنية العامة للنظام

            // قيد فريد لمنع تكرار نفس فئة السعر لنفس وحدة الصنف
            $table->unique(['item_unit_id', 'price_list_id'], 'unit_price_list_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_unit_prices');
    }
};
