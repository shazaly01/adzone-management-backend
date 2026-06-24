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
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            // رقم القيد أو السند التلقائي (مثال: JV-00001 أو REC-00001)
            $table->string('entry_number')->unique();
            // تاريخ الاستحقاق أو الحركة المالية
            $table->date('entry_date');
            // نوع المعاملة لسهولة الفرز البرمجي (قيد تسوية، سند صرف، سند قبض)
            $table->enum('type', ['journal', 'receipt', 'payment'])->default('journal');
            // البيان العام أو الملاحظات الشاملة للحركة
            $table->text('notes')->nullable();

            // معرف المستخدم الذي قام بإنشاء القيد
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');

            $table->softDeletes(); // تفعيل الحذف الناعم لسلامة القيود
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
