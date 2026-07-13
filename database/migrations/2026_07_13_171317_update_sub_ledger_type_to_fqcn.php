<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * تشغيل الهجرة لتطهير البيانات التاريخية وتحويلها لكلاسات كاملة
     */
    public function up(): void
    {
        // مصفوفة المطابقة الثابتة لترجمة الأسماء القديمة إلى الكلاسات الكاملة
        $mappings = [
            'customer' => \App\Models\Customer::class,
            'client'   => \App\Models\Customer::class,
            'supplier' => \App\Models\Supplier::class,
            'treasury' => \App\Models\Treasury::class,
            'bank'     => \App\Models\Bank::class,
            'expense'  => \App\Models\Expense::class,
            'user'     => \App\Models\User::class,
            'designer' => \App\Models\User::class,
        ];

        // 1. تطهير جدول أسطر قيود اليومية
        foreach ($mappings as $shortName => $fqcn) {
            DB::table('journal_entry_lines')
                ->where(DB::raw('LOWER(TRIM(sub_ledger_type))'), $shortName)
                ->update(['sub_ledger_type' => $fqcn]);
        }

        // 2. تطهير جدول سندات القبض والصرف
        foreach ($mappings as $shortName => $fqcn) {
            DB::table('vouchers')
                ->where(DB::raw('LOWER(TRIM(sub_ledger_type))'), $shortName)
                ->update(['sub_ledger_type' => $fqcn]);
        }
    }

    /**
     * التراجع عن الهجرة (إعادة الكلاسات الكاملة إلى أسماء مفردة إذا لزم الأمر)
     */
    public function down(): void
    {
        $mappings = [
            \App\Models\Customer::class => 'customer',
            \App\Models\Supplier::class => 'supplier',
            \App\Models\Treasury::class => 'treasury',
            \App\Models\Bank::class     => 'bank',
            \App\Models\Expense::class  => 'expense',
            \App\Models\User::class     => 'user',
        ];

        foreach ($mappings as $fqcn => $shortName) {
            // تراجع جدول أسطر القيود
            DB::table('journal_entry_lines')
                ->where('sub_ledger_type', $fqcn)
                ->update(['sub_ledger_type' => $shortName]);

            // تراجع جدول السندات
            DB::table('vouchers')
                ->where('sub_ledger_type', $fqcn)
                ->update(['sub_ledger_type' => $shortName]);
        }
    }
};
