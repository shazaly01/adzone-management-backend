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
        Schema::create('item_barcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();

            // ربط الباركود بالوحدة المعنية للصنف لضمان دقة القراءة والبيع
            $table->foreignId('item_unit_id')->constrained('item_units')->cascadeOnDelete();

            // حقل الباركود فريد ومفهرس لضمان سرعة البحث الصاروخية في الفواتير
            $table->string('barcode')->unique()->index();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_barcodes');
    }
};
