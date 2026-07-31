<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\WhatsApp\QueryHandlerRegistry;
use App\Services\WhatsApp\Handlers\SalesQueryHandler;
use App\Services\WhatsApp\Handlers\InventoryQueryHandler;
use App\Services\WhatsApp\Handlers\PartyBalanceQueryHandler;
use App\Services\WhatsApp\Handlers\ItemStockQueryHandler;

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

            // تسجيل معالجات الاستعلامات المتاحة
            $registry->register($app->make(SalesQueryHandler::class));
            $registry->register($app->make(PartyBalanceQueryHandler::class));
            $registry->register($app->make(ItemStockQueryHandler::class));

            return $registry;
        });
    }
}
