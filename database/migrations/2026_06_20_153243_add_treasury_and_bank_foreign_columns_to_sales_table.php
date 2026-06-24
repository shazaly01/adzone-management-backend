<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تشغيل الهجرة لحقن وتثبيت الأعمدة المالية بجدول المبيعات
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // إضافة الأعمدة اللوجستية والمالية الجديدة بعد حقل المستودع مباشرة
            $table->foreignId('treasury_id')->nullable()->after('store_id')->constrained('treasuries')->nullOnDelete();
            $table->foreignId('bank_id')->nullable()->after('treasury_id')->constrained('banks')->nullOnDelete();
        });
    }

    /**
     * التراجع عن الهجرة وحذف الأعمدة والروابط الأجنبية بأمان
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['treasury_id']);
            $table->dropColumn('treasury_id');

            $table->dropForeign(['bank_id']);
            $table->dropColumn('bank_id');
        });
    }
};
