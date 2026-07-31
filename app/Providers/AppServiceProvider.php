<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\WhatsApp\QueryHandlerRegistry;
use App\Services\WhatsApp\Handlers\SalesQueryHandler;
use App\Services\WhatsApp\Handlers\PartyBalanceQueryHandler;
use App\Services\WhatsApp\Handlers\ItemStockQueryHandler;
use App\Services\WhatsApp\Handlers\LatestInvoiceQueryHandler;
use App\Services\WhatsApp\Handlers\TopDebtorsQueryHandler;
use App\Services\WhatsApp\Handlers\LowStockQueryHandler;
use App\Services\WhatsApp\Handlers\MaterialConsumptionQueryHandler;

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
        // تسجيل سجل استعلامات الواتساب المركزي كـ Singleton
        $this->app->singleton(QueryHandlerRegistry::class, function ($app) {
            $registry = new QueryHandlerRegistry();

            // تسجيل معالجات الاستعلامات المتاحة في النظام
            $registry->register($app->make(SalesQueryHandler::class));
            $registry->register($app->make(PartyBalanceQueryHandler::class));
            $registry->register($app->make(ItemStockQueryHandler::class));
            $registry->register($app->make(LatestInvoiceQueryHandler::class));
            $registry->register($app->make(TopDebtorsQueryHandler::class));
            $registry->register($app->make(LowStockQueryHandler::class));
            $registry->register($app->make(MaterialConsumptionQueryHandler::class));

            return $registry;
        });
    }
}
