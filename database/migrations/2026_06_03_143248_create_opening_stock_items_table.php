<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تشغيل الهجرة لبناء جدول تفاصيل الأرصدة الافتتاحية التأسيسية
     */
    public function up(): void
    {
        Schema::create('opening_stock_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opening_stock_id')->constrained('opening_stocks')->onDelete('cascade'); // الربط برأس المستند
            $table->foreignId('item_id')->constrained('items')->onDelete('restrict'); // الصنف

            // التعديل المعماري: ربط السطر بمصفوفة وحدات الصنف بدلاً من جدول الوحدات المجرد
            $table->foreignId('item_unit_id')->constrained('item_units')->onDelete('restrict');

            $table->decimal('quantity', 15, 4); // الكمية الافتتاحية بدقة 4 علامات عشرية للأوزان والكسور
            $table->decimal('unit_cost', 15, 2); // تكلفة الوحدة الواحدة وقت الافتتاح في المصفوفة
            $table->decimal('subtotal', 15, 2); // إجمالي قيمة السطر (الكمية × التكلفة)
            $table->timestamps();

            // الفهارس لتسريع عملية تحميل الأرصدة الافتتاحية وتقارير ميزان المراجعة التأسيسي
            $table->index('opening_stock_id', 'os_items_opening_stock_id_idx');
            $table->index('item_id');
            $table->index('item_unit_id');
        });
    }

    /**
     * التراجع عن الهجرة
     */
    public function down(): void
    {
        Schema::dropIfExists('opening_stock_items');
    }
};
