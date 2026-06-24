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
        Schema::table('sale_items', function (Blueprint $table) {
            // إضافة حقول الطول والعرض وتكون قابلة لـ null لتناسب الأصناف غير المترية
            $table->decimal('length', 10, 2)->nullable()->after('item_unit_id');
            $table->decimal('width', 10, 2)->nullable()->after('length');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['length', 'width']);
        });
    }
};
