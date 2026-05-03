<?php

namespace App\Providers;

use App\Events\NotificationCreated;
use App\Listeners\CheckEscalation;
use App\Listeners\UpdateSubscriberStats;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(NotificationCreated::class, UpdateSubscriberStats::class);
        Event::listen(NotificationCreated::class, CheckEscalation::class);
    }
}
