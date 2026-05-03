<?php

namespace AlertMetrics;

use Illuminate\Support\ServiceProvider;

class MetricsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(MetricsAggregator::class, function ($app) {
            return new MetricsAggregator();
        });

        $this->app->singleton(SubscriberResolver::class, function ($app) {
            return new SubscriberResolver();
        });

        $this->app->singleton(EngagementScorer::class, function ($app) {
            return new EngagementScorer();
        });

        $this->app->singleton(DigestScheduler::class, function ($app) {
            return new DigestScheduler(
                $app->make(MetricsAggregator::class),
                $app->make(EngagementScorer::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerEventListeners();
    }

    /**
     * Register the event listeners for the digest system.
     *
     * Note: These listeners handle the digest scheduling workflow.
     * When a digest is scheduled, we need to generate a reference ID,
     * calculate the digest window, and assign priority.
     */
    protected function registerEventListeners(): void
    {
        $listen = [
            Events\DigestScheduled::class => [
                Listeners\GenerateDigestId::class,
                Listeners\CalculateDigestWindow::class,
                Listeners\AssignDigestPriority::class,
            ],
        ];

        foreach ($listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                \Illuminate\Support\Facades\Event::listen($event, $listener);
            }
        }
    }
}
