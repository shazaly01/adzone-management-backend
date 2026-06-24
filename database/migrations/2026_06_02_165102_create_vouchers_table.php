<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تشغيل الهجرة لبناء جدول السندات المالية الموحد
     */
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->enum('voucher_type', ['payment', 'receipt']); // نوع السند: payment (صرف)، receipt (قبض)
            $table->integer('voucher_sequence');
            $table->string('voucher_number')->unique(); // الرمز النظيف المنسق (PAY-0001 أو REC-0001)

            // الحساب المستهدف (مثل بند المصروف في الصرف، أو بند الإيراد في القبض، أو حساب ذمة)
            $table->foreignId('account_id')->constrained('accounts')->onDelete('restrict');

            // وسيط الحسابات المساعدة (Sub-Ledger) لربط السند بمورد، عميل، موظف معين إن وجد
            $table->string('sub_ledger_type')->nullable();
            $table->unsignedBigInteger('sub_ledger_id')->nullable();

            $table->enum('payment_method', ['cash', 'bank']); // طريقة المعاملة: كاش أو بنك

            // الصندوق الفرعي أو البنك الفرعي الذي خرجت منه أو دخلت إليه الأموال (حساب النقدية المقابل)
            $table->foreignId('fund_account_id')->constrained('accounts')->onDelete('restrict');

            $table->decimal('amount', 15, 2); // القيمة المالية الصافية للسند
            $table->dateTime('voucher_date'); // تاريخ ووقت تنظيم السند
            $table->text('notes')->nullable(); // البيان أو الشرح الخاص بالسند

            $table->foreignId('user_id')->constrained('users')->onDelete('restrict'); // الموظف منشئ السند
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->onDelete('set null'); // القيد المالي الناتج

            $table->timestamps();
            $table->softDeletes(); // الدعم المعماري للحذف المؤرشف

            // الفهارس لسرعة الاستعلام والتقارير المالية
            $table->index(['sub_ledger_type', 'sub_ledger_id']);
            $table->index('voucher_date');
            $table->index('voucher_type');
        });
    }

    /**
     * التراجع عن الهجرة
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
