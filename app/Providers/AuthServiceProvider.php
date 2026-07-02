<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Treasury;
use App\Models\Message;
use App\Models\Purchase;
use App\Models\Sale;
use App\Policies\UserPolicy;
use App\Policies\RolePolicy;
use App\Policies\TreasuryPolicy;
use App\Policies\MessagePolicy;
use App\Policies\PurchasePolicy;
use App\Policies\SalePolicy;
use App\Policies\DashboardPolicy;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * رسم خارطة النماذج والسياسات الخاصة بها.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // إدارة المستخدمين والأدوار
        User::class => UserPolicy::class,
        Role::class => RolePolicy::class,

        // نظام المساعدات والخزينة والمشتريات الجذري
        Message::class  => MessagePolicy::class,
        Treasury::class => TreasuryPolicy::class,
        Purchase::class => PurchasePolicy::class,
        Sale::class => SalePolicy::class,
        \App\Models\Voucher::class => \App\Policies\VoucherPolicy::class,
        \App\Models\JournalEntry::class => \App\Policies\JournalEntryPolicy::class,
        \App\Models\StockAdjustment::class => \App\Policies\StockAdjustmentPolicy::class,
        \App\Models\OpeningStock::class => \App\Policies\OpeningStockPolicy::class,

        // تسجيل سياسة لوحة التحكم لتعمل بشكل مستقل بدون موديل مرتبك
        DashboardPolicy::class => DashboardPolicy::class,
    ];

    /**
     * تسجيل أي خدمات مصادقة أو تصريح.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // منح الـ Super Admin صلاحية الوصول الكامل لكل شيء دون فحص الصلاحيات
        Gate::before(function ($user, $ability) {
            return $user->hasRole('Super Admin') ? true : null;
        });
    }
}
