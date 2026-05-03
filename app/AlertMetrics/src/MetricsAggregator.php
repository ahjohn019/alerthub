<?php

namespace AlertMetrics;

use App\Models\Notification;
use Illuminate\Support\Facades\Cache;

class MetricsAggregator
{
    /**
     * Get the count of alerts for a given date.
     *
     * Used by the dashboard and digest scheduler to track alert volume.
     * Results are cached for 1 hour to reduce database load.
     *
     * @param  int     $projectId  Project to scope metrics to
     * @param  string  $date       Date in Y-m-d format
     * @return int
     */
    public function getDailyAlertCount(int $projectId, string $date): int
    {
        $cacheKey = "alert-metrics::project::{$projectId}::{$date}";

        return Cache::remember($cacheKey, 3600, function () use ($projectId, $date) {
            return Notification::withoutGlobalScopes()
                ->where('project_id', $projectId)
                ->whereDate('created_at', $date)
                ->count();
        });
    }

    /**
     * Get hourly breakdown of alert counts for a date.
     *
     * @param  int     $projectId  Project to scope metrics to
     * @param  string  $date       Date in Y-m-d format
     * @return array
     */
    public function getHourlyBreakdown(int $projectId, string $date): array
    {
        $cacheKey = "alert-metrics::project::{$projectId}::hourly::{$date}";

        return Cache::remember($cacheKey, 1800, function () use ($projectId, $date) {
            return Notification::withoutGlobalScopes()
                ->where('project_id', $projectId)
                ->whereDate('created_at', $date)
                ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
                ->groupByRaw('HOUR(created_at)')
                ->pluck('count', 'hour')
                ->toArray();
        });
    }

    /**
     * Record that an alert was processed.
     *
     * Increments the daily counter and invalidates stale cache.
     *
     * @param  int  $notificationId
     * @return void
     */
    public function recordAlert(int $notificationId): void
    {
        $notification = Notification::withoutGlobalScopes()->find($notificationId);

        if (! $notification) {
            return;
        }

        $date = $notification->created_at?->toDateString() ?? now()->toDateString();
        $projectId = (int) $notification->project_id;
        $counterKey = "alert-metrics::counter::project::{$projectId}::{$date}";

        Cache::increment($counterKey);

        // Invalidate the cached count so next read is fresh
        Cache::forget("alert-metrics::project::{$projectId}::{$date}");
        Cache::forget("alert-metrics::project::{$projectId}::hourly::{$date}");
    }

    /**
     * Get alert count for a specific time window.
     *
     * @param  int     $projectId
     * @param  string  $startDate
     * @param  string  $endDate
     * @return int
     */
    public function getAlertCountForWindow(int $projectId, string $startDate, string $endDate): int
    {
        return Notification::withoutGlobalScopes()
            ->where('project_id', $projectId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
    }
}
