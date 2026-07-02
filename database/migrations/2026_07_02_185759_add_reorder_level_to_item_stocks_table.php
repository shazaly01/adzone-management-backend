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
        Schema::table('item_stocks', function (Blueprint $table) {
            // إضافة حقل حد الطلب كـ float بقيمة افتراضية صفر بعد حقل الكمية الحالية
            $table->float('reorder_level')->default(0.00)->after('current_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_stocks', function (Blueprint $table) {
            $table->dropColumn('reorder_level');
        });
    }
};
