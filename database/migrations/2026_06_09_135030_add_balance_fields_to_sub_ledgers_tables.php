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
        // قائمة بجداول الحسابات المساعدة لتحديثها معاً بنسق موحد
        $subLedgerTables = ['banks', 'customers', 'suppliers', 'treasuries'];

        foreach ($subLedgerTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'opening_balance')) {
                    $table->decimal('opening_balance', 15, 2)->default(0.00)->after('account_id');
                }
                if (!Schema::hasColumn($tableName, 'current_balance')) {
                    $table->decimal('current_balance', 15, 2)->default(0.00)->after('opening_balance');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $subLedgerTables = ['banks', 'customers', 'suppliers', 'treasuries'];

        foreach ($subLedgerTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['opening_balance', 'current_balance']);
            });
        }
    }
};
