<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تشغيل الهجرة لبناء جدول مستندات التسوية الجردية (الرأس)
     */
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->integer('adjustment_sequence');
            $table->string('adjustment_number')->unique(); // الرمز النظيف المنسق مثل (ADJ-0001)

            // المستودع الخاضع لعملية الجرد والتسوية
            $table->foreignId('store_id')->constrained('stores')->onDelete('restrict');

            $table->dateTime('adjustment_date'); // تاريخ ووقت تنظيم التسوية الجردية
            $table->text('notes')->nullable(); // أسباب التسوية (تالف، سرقة، فروقات جرد سنوي)

            $table->foreignId('user_id')->constrained('users')->onDelete('restrict'); // الموظف المسؤول عن الجرد
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->onDelete('set null'); // القيد المالي الناتج عن التسوية

            $table->timestamps();
            $table->softDeletes(); // الدعم المعماري للأرشفة والحذف المؤقت

            // الفهارس لسرعة التقارير المخزنية
            $table->index('adjustment_date');
            $table->index('store_id');
        });
    }

    /**
     * التراجع عن الهجرة
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
