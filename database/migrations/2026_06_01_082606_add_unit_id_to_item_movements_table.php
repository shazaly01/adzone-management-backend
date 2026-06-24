<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تشغيل الهجرة لإضافة الحقل الجديد
     */
    public function up(): void
    {
        Schema::table('item_movements', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('store_id')->constrained('units')->onDelete('restrict');
        });
    }

    /**
     * التراجع عن الهجرة
     */
    public function down(): void
    {
        Schema::table('item_movements', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn('unit_id');
        });
    }
};
