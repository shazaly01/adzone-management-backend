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
            $table->foreignId('store_id')
                ->nullable()
                ->after('type')
                ->constrained('stores')
                ->nullOnDelete();

            $table->foreignId('treasury_id')
                ->nullable()
                ->after('store_id')
                ->constrained('treasuries')
                ->nullOnDelete();

            $table->foreignId('bank_id')
                ->nullable()
                ->after('treasury_id')
                ->constrained('banks')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropForeign(['treasury_id']);
            $table->dropForeign(['bank_id']);
            $table->dropColumn(['store_id', 'treasury_id', 'bank_id']);
        });
    }
};
