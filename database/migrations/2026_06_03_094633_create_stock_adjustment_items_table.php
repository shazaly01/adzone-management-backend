<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تشغيل الهجرة لبناء جدول تفاصيل الأصناف المتأثرة بالتسوية الجردية
     */
    public function up(): void
    {
        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained('stock_adjustments')->onDelete('cascade');
            $table->foreignId('item_id')->constrained('items')->onDelete('restrict');

            // التعديل المعماري: ربط السطر بمصفوفة وحدات الصنف بدلاً من جدول الوحدات المجرد
            $table->foreignId('item_unit_id')->constrained('item_units')->onDelete('restrict');

            // تفاصيل الكميات والكسور بدقة مطابقة للنظام المخزني
            $table->decimal('book_quantity', 15, 4); // الكمية الدفترية المتوفرة في النظام وقت الجرد
            $table->decimal('physical_quantity', 15, 4); // الكمية الفعلية الموجودة على أرض الواقع
            $table->decimal('quantity_difference', 15, 4); // الفارق المحتسب (فعلية - دفترية) قد يكون موجباً أو سالباً

            $table->decimal('unit_cost', 15, 2); // سعر تكلفة الوحدة وقت الحركة لاحتساب قيمة العجز أو الفائض مالياً
            $table->timestamps();

            // الفهارس لتأمين سرعة عمليات الفحص والجرد المتكرر
            $table->index('stock_adjustment_id', 'sa_items_adjustment_id_idx');
            $table->index('item_id');
            $table->index('item_unit_id');
        });
    }

    /**
     * التراجع عن الهجرة
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
    }
};
