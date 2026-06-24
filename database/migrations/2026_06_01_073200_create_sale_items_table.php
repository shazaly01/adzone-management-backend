<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->onDelete('cascade');
            $table->foreignId('item_id')->constrained('items')->onDelete('restrict');

            // التعديل المعماري: ربط السطر بمصفوفة وحدات الصنف بدلاً من جدول الوحدات المجرد
            $table->foreignId('item_unit_id')->constrained('item_units')->onDelete('restrict');

            $table->decimal('quantity', 15, 4)->default(0.0000);
            $table->decimal('unit_price', 15, 2)->default(0.00);

            // الحسابات المباشرة والخفيفة للسطر
            $table->decimal('subtotal', 15, 2)->default(0.00);
            $table->decimal('discount_amount', 15, 2)->default(0.00);
            $table->decimal('grand_total', 15, 2)->default(0.00)->comment('الصافي للسطر (الكمية * السعر - الخصم)');

            $table->timestamps();

            // الفهارس المحدثة لتأمين كفاءة الاستعلامات ونقاط البيع الفورية
            $table->index('sale_id');
            $table->index('item_id');
            $table->index('item_unit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
