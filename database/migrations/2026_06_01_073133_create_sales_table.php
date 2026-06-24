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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->enum('invoice_type', ['sale', 'return']);
            $table->integer('invoice_sequence');
            $table->string('invoice_number')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('sales')->onDelete('restrict');

            $table->foreignId('store_id')->constrained('stores')->onDelete('restrict');

            // [التعديل الأول]: ربط العميل بجدول العملاء المستقل بدلاً من شجرة الحسابات
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');

            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');

            // [التعديل الثاني]: إضافة حقل القيد المالي لربط الفاتورة بالنظام المحاسبي
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->dateTime('invoice_date');
            $table->enum('payment_type', ['cash', 'credit'])->default('cash');

            // المجمعات المالية للفاتورة
            $table->decimal('subtotal', 15, 2)->default(0.00);
            $table->decimal('discount_amount', 15, 2)->default(0.00);
            $table->decimal('tax_amount', 15, 2)->default(0.00)->comment('قيمة الضريبة الإجمالية للفاتورة بالكامل');
            $table->decimal('grand_total', 15, 2)->default(0.00);

            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('invoice_type');
            $table->index('invoice_sequence');
            $table->index('invoice_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
