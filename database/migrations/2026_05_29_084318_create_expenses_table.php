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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('اسم بند المصروف مثل: إيجار، كهرباء، رواتب');
            // الربط الإلزامي مع شجرة الحسابات ليصب فيها تلقائياً
            $table->foreignId('account_id')->constrained('accounts')->onDelete('restrict');
            $table->boolean('is_active')->default(true);
            $table->softDeletes(); // التدمير الناعم لحماية البند من الحذف العشوائي في حال وجود حركات مالية
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
