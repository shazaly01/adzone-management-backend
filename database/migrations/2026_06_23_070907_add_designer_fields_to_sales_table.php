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
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('designer_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->decimal('designer_meter_price', 15, 2)->nullable()->after('notes');
            $table->decimal('design_commission', 15, 2)->nullable()->after('designer_meter_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['designer_id']);
            $table->dropColumn(['designer_id', 'designer_meter_price', 'design_commission']);
        });
    }
};
