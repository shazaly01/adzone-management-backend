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
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();

            // الربط مع رأس القيد أو السند
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->onDelete('cascade');

            // الربط مع الحساب المتأثر في شجرة الحسابات
            $table->foreignId('account_id')->constrained('accounts')->onDelete('restrict');

            // [التعديل الجوهري]: إضافة أعمدة الكيانات الفرعية (البنوك، العملاء، الخ)
            // هذه الدالة تنشئ عمودين: sub_ledger_type (varchar) و sub_ledger_id (bigint)
            $table->nullableMorphs('sub_ledger');

            // المبالغ المالية (مدين ودائن)
            $table->decimal('debit', 15, 2)->default(0.00)->comment('المبلغ المدين - الآخذ');
            $table->decimal('credit', 15, 2)->default(0.00)->comment('المبلغ الدائن - المعطي');

            // ملاحظات خاصة بالسطر نفسه (البيان التحليلي للسطر)
            $table->string('line_notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
    }
};
