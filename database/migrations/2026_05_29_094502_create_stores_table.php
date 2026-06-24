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
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('اسم المستودع أو المخزن');
            $table->string('location')->nullable()->comment('موقع أو عنوان المستودع جغرافيّاً');

            // الربط الإلزامي مع شجرة الحسابات ليصب فيها كأصل متداول تلقائياً
            $table->foreignId('account_id')->constrained('accounts')->onDelete('restrict');

            $table->boolean('is_active')->default(true)->comment('حالة المخزن (نشط / معطل)');
            $table->softDeletes(); // التدمير الناعم لضمان سلامة البيانات والمخزون التاريخي
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
