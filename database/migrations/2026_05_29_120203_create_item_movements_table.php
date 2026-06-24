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
        Schema::create('item_movements', function (Blueprint $table) {
            $table->id();

            // ربط الحركة بالصنف والمخزن المعني
            $table->foreignId('item_id')->constrained('items')->onDelete('restrict');
            $table->foreignId('store_id')->constrained('stores')->onDelete('restrict');

            // التعديل المعماري الحتمي: ربط كرت الحركة بمصفوفة وحدات الصنف بدلاً من جدول الوحدات المجرد
            $table->foreignId('item_unit_id')->constrained('item_units')->onDelete('restrict');

            // تعديل 'opening' إلى 'opening_stock' ليتطابق هندسياً مع ما تبثه الخدمة ويمنع انهيار قواعد البيانات
            $table->enum('movement_type', [
                'opening_stock', // رصيد افتتاحي للمخزن
                'purchase',      // فاتورة مشتريات واردة
                'sales',         // فاتورة مبيعات صادرة
                'transfer_in',   // تحويل وارد من مخزن آخر
                'transfer_out',  // تحويل صادر إلى مخزن آخر
                'adjustment'     // تسوية جردية (زيادة أو عجز)
            ])->comment('نوع الحركة المخزنية');

            // توثيق المرجع (مثال: رقم الفاتورة، رقم سند التحويل، رقم سند الجرد) لسهولة التتبع
            $table->string('document_no')->nullable()->comment('رقم المستند المرجعي المسبب للحركة');

            // الكميات والوحدات كما أدخلها المستخدم في الشاشة
            $table->string('unit_name_used')->comment('اسم الوحدة المستخدمة في الحركة (قطعة، ربطة، صندوق)');
            $table->decimal('quantity', 15, 4)->comment('الكمية المدخلة بالوحدة المستخدمة (موجبة للوارد، سالبة للصادر)');

            // معامل التحويل للوحدة الصغرى وقت الحركة لضمان دقة الحسابات اللوجستية
            $table->decimal('unit_factor', 10, 2)->default(1.00)->comment('معامل تحويل هذه الوحدة إلى الوحدة الصغرى الأساسية');

            // صافي الكمية الفعلي مقاساً بالوحدة الصغرى (Quantity * Unit Factor) ليتم قراءته وتحديث المخزون اللحظي بناءً عليه
            $table->decimal('base_quantity', 15, 4)->comment('الكمية الصافية الفعلية بالوحدة الصغرى الأساسية');

            // سعر التكلفة الفعلي وقت الحركة (مهم جداً لحساب تقييم المخزون وأرباح المبيعات لاحقاً)
            $table->decimal('cost_price', 15, 2)->default(0.00)->comment('سعر تكلفة الوحدة الصغرى وقت الحركة');

            // بيانات إضافية (ملاحظات الحركة)
            $table->text('notes')->nullable()->comment('ملاحظات تفصيلية حول الحركة المخزنية');

            // ربط الحركة بالمستخدم الذي قام بالعملية للأمان والرقابة
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');

            $table->timestamps();

            // الفهارس (Indexes) المفهرسة لتسريع عمليات الفلترة واستخراج كرت الصنف والتقارير المخزنية الكبيرة
            $table->index(['item_id', 'store_id', 'movement_type'], 'item_store_mov_type_idx');
            $table->index('item_unit_id');
            $table->index('document_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_movements');
    }
};
