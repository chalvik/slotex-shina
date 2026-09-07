<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Bitrix24\Bitrix24Service;
use App\Services\Bitrix24\LeadService;
use App\Services\Bitrix24\DealService;
use App\Services\Bitrix24\UserService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Bitrix24Service::class, function ($app) {
            return new Bitrix24Service();
        });

        $this->app->singleton(LeadService::class, function ($app) {
            return new LeadService($app->make(Bitrix24Service::class));
        });

        $this->app->singleton(DealService::class, function ($app) {
            return new DealService($app->make(Bitrix24Service::class));
        });

        $this->app->singleton(UserService::class, function ($app) {
            return new UserService($app->make(Bitrix24Service::class));
        });
    }
    public function boot(): void
    {
        //
    }
}