<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تشغيل الهجرة لتوسيع خيارات الدفع لتشغيل الدفع الإلكتروني في المشتريات
     */
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            // تعديل الحقل ليستقبل الخيار الجديد 'card' بشكل رسمي في قاعدة البيانات
            $table->enum('payment_type', ['cash', 'card', 'credit'])->change();
        });
    }

    /**
     * التراجع عن الهجرة وإعادة الحقل لحالته الأصلية
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->enum('payment_type', ['cash', 'credit'])->change();
        });
    }
};
