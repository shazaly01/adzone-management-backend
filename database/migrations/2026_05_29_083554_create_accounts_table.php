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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            // علاقة الحساب الأب لبناء الشجرة (Null للحسابات الرئيسية المتفرعة من النظام)
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->onDelete('restrict');
            $table->string('name');
            $table->string('code')->unique()->comment('المعرف أو الكود المحاسبي للحساب');
            $table->enum('type', ['system', 'cash', 'bank', 'customer', 'supplier', 'expense', 'income', 'normal'])
                  ->default('normal')
                  ->comment('نوع الحساب لتسهيل الفلترة والربط البرمجي');

            // الأرصدة
            $table->decimal('opening_balance', 15, 2)->default(0.00);
            $table->decimal('current_balance', 15, 2)->default(0.00);

            $table->softDeletes(); // التدمير الناعم لضمان سلامة الحسابات المالية
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
