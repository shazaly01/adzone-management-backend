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
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('account_number')->nullable()->comment('رقم الحساب البنكي الخاص بالمنشأة');
            $table->string('iban')->nullable()->comment('رقم الآيبان البنكي');
            // الربط الإلزامي مع شجرة الحسابات
            $table->foreignId('account_id')->constrained('accounts')->onDelete('restrict');
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
