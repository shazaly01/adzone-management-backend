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
            // حقل تصنيف البيع (داخلي/خارجي) مفهرس لسرعة الاستعلام
            $table->string('sale_type')->default('indoor')->after('invoice_type')->index();

            // حقل نصي حر لكتابة اسم العميل الخارجي مباشرة دون ربطه بسجل العملاء
            $table->string('customer_name_text')->nullable()->after('sale_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['sales_sale_type_index']);
            $table->dropColumn(['sale_type', 'customer_name_text']);
        });
    }
};
