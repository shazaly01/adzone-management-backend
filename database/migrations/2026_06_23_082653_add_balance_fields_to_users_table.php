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
        Schema::table('users', function (Blueprint $table) {
            // إضافة حقول الأرصدة ليتوافق جدول المستخدمين مع نظام الحسابات المساعدة التلقائي
            $table->decimal('opening_balance', 15, 4)->default(0.0000)->after('bank_id');
            $table->decimal('current_balance', 15, 4)->default(0.0000)->after('opening_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['opening_balance', 'current_balance']);
        });
    }
};
