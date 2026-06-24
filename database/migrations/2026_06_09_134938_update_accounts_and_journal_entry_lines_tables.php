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
        // 1. تحديث جدول الحسابات لإضافة حقل طبيعة الحساب صراحة
        Schema::table('accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('accounts', 'nature')) {
                $table->enum('nature', ['debit', 'credit'])->default('debit')->after('type');
            }
        });

        // 2. تحديث جدول أسطر القيود لدعم الحذف الناعم لمنع الأسطر اليتيمة
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('journal_entry_lines', 'deleted_at')) {
                $table->softDeletes()->after('line_notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('nature');
        });

        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
