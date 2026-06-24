<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تشغيل الهجرة لحقن الحسابات المساعدة للنقدية بدون ترقيع
     */
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreignId('treasury_id')
                ->nullable()
                ->after('payment_method')
                ->constrained('treasuries')
                ->onDelete('restrict');

            $table->foreignId('bank_id')
                ->nullable()
                ->after('treasury_id')
                ->constrained('banks')
                ->onDelete('restrict');
        });
    }

    /**
     * التراجع عن الهجرة
     */
    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropForeign(['treasury_id']);
            $table->dropForeign(['bank_id']);
            $table->dropColumn(['treasury_id', 'bank_id']);
        });
    }
};
