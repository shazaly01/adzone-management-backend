<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->enum('invoice_type', ['purchase', 'return']);
            $table->integer('invoice_sequence');
            $table->string('invoice_number')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('purchases')->onDelete('restrict');

            $table->foreignId('store_id')->constrained('stores')->onDelete('restrict');

            // [إصلاح]: الارتباط بجدول الموردين المستقل
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('restrict');

            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');

            // [إصلاح]: إضافة حقل القيد المالي المفقود
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->dateTime('invoice_date');
            $table->enum('payment_type', ['cash', 'credit'])->default('cash');

            $table->decimal('subtotal', 15, 2)->default(0.00);
            $table->decimal('discount_amount', 15, 2)->default(0.00);
            $table->decimal('tax_amount', 15, 2)->default(0.00);
            $table->decimal('grand_total', 15, 2)->default(0.00);

            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('invoice_type');
            $table->index('invoice_sequence');
            $table->index('invoice_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
