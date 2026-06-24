<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // استخدام الاستعلام المباشر (Raw SQL) لتعديل عمود الـ ENUM وإضافة النوع الجديد بأمان وموثوقية عالية في محرك MySQL
        DB::statement("ALTER TABLE `journal_entries` MODIFY COLUMN `type` ENUM('journal', 'receipt', 'payment', 'opening_balance') NOT NULL DEFAULT 'journal'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // إعادة الحقل إلى حالته الأصلية عند التراجع (تأكد محاسبياً من عدم وجود قيود 'opening_balance' قبل التراجع)
        DB::statement("ALTER TABLE `journal_entries` MODIFY COLUMN `type` ENUM('journal', 'receipt', 'payment') NOT NULL DEFAULT 'journal'");
    }
};
