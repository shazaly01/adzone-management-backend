<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /**
         * ربط المسميات المختصرة للحسابات المساعدة بفئاتها البرمجية الكاملة
         * هذا السطر يؤمن نجاح دالة class_exists داخل خدمة القيود لجميع الكيانات
         */
        Relation::morphMap([
            'customer' => \App\Models\Customer::class,
            'supplier' => \App\Models\Supplier::class,
            'treasury' => \App\Models\Treasury::class,
            'bank'     => \App\Models\Bank::class,
            'user'     => \App\Models\User::class,
            'designer' => \App\Models\User::class,
            'expense'  => \App\Models\Expense::class,
        ]);
    }
}
