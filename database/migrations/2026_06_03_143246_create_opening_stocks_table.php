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
       Schema::create('opening_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('opening_number')->unique(); // رقم المستند المتسلسل الافتتاحي مثل OS-2026-001
            $table->foreignId('store_id')->constrained('stores')->onDelete('restrict'); // المخزن المستهدف بالحقن
            $table->dateTime('opening_date'); // تاريخ إدخال الرصيد الافتتاحي
            $table->text('notes')->nullable(); // ملاحظات المستند
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->onDelete('set null'); // الربط بالقيد المالي التلقائي الناتج
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict'); // المستخدم المنشئ للمستند
            $table->timestamps();
            $table->softDeletes(); // الحذف الناعم لسلامة الرقابة الجردية
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opening_stocks');
    }
};
