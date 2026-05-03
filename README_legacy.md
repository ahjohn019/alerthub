# AlertMetrics — Legacy Integration Package

This package provides metrics, subscriber resolution, engagement scoring, and digest scheduling for the AlertHub platform. It was developed by a previous team member and needs to be integrated into your application.

## Package Structure

```
src/
├── MetricsServiceProvider.php    # Service provider (register in config/app.php)
├── MetricsAggregator.php         # Alert counting and metrics tracking
├── SubscriberResolver.php        # Webhook payload → Subscriber resolution
├── ProcessAlertDigest.php        # Queue job for batched alert digests
├── EngagementScorer.php          # Subscriber engagement scoring
├── DigestScheduler.php           # Orchestrates digest scheduling
├── Events/
│   └── DigestScheduled.php       # Event fired when digest is scheduled
└── Listeners/
    ├── GenerateDigestId.php      # Generates unique digest reference ID
    ├── CalculateDigestWindow.php # Calculates optimal delivery window
    └── AssignDigestPriority.php  # Assigns priority based on volume/severity
```

## Installation

### 1. Copy Files
Copy the `src/` directory contents into your application. Recommended location: `app/AlertMetrics/` or as a package in `packages/alert-metrics/src/`.

If using `app/AlertMetrics/`, update the namespace from `AlertMetrics` to `App\AlertMetrics` in all files. If using the packages directory, add a PSR-4 autoload entry to your `composer.json`:

```json
{
    "autoload": {
        "psr-4": {
            "AlertMetrics\\": "packages/alert-metrics/src/"
        }
    }
}
```

Then run `composer dump-autoload`.

### 2. Register Service Provider

Add to `config/app.php` providers array:

```php
'providers' => [
    // ...
    AlertMetrics\MetricsServiceProvider::class,
],
```

Or if using Laravel 11+ auto-discovery, add to `bootstrap/providers.php`.

### 3. Integration Points

#### SubscriberResolver
Wire into your webhook processing pipeline's subscriber matching step. The resolver accepts a project ID and the raw webhook payload, then finds or creates the appropriate subscriber.

```php
// In your SubscriberMatchHandler or equivalent:
$resolver = app(AlertMetrics\SubscriberResolver::class);
$subscriber = $resolver->resolve($projectId, $webhookPayload);
```

#### MetricsAggregator
Call `recordAlert()` whenever a notification is created to track alert volume:

```php
// In your NotificationCreated event listener or equivalent:
$aggregator = app(AlertMetrics\MetricsAggregator::class);
$aggregator->recordAlert($notification->id);
```

Use `getDailyAlertCount()` and `getHourlyBreakdown()` for dashboard/API metrics.

#### DigestScheduler
Call from a scheduled command or after notification creation to batch alerts:

```php
// In a scheduled command (e.g., daily at midnight):
$scheduler = app(AlertMetrics\DigestScheduler::class);
$count = $scheduler->scheduleDigests($projectId, now()->toDateString(), 'daily');
```

#### EngagementScorer
Include engagement scores in subscriber API responses:

```php
// In your Subscriber resource class:
$scorer = app(AlertMetrics\EngagementScorer::class);

return [
    'id' => $this->id,
    'email' => $this->email,
    'engagement_score' => $scorer->calculateScore($this->id, 'realtime'),
    'engagement_tier' => $scorer->getTier(
        $scorer->calculateScore($this->id, 'realtime')
    ),
    // ...
];
```

## Dependencies

This package expects the following models to exist in your application:
- `App\Models\Subscriber` with columns: `id`, `project_id`, `email`, `external_id`, `name`, `notification_count`, `last_notified_at`, `metadata`
- `App\Models\Notification` with columns: `id`, `project_id`, `subscriber_id`, `subject`, `status`, `payload`, `created_at`

The subscriber model should have a `notifications` relationship:

```php
public function notifications()
{
    return $this->hasMany(Notification::class);
}
```

## Queue Configuration

The `ProcessAlertDigest` job implements `ShouldQueue` and `ShouldBeUnique`. Ensure your queue driver (Redis recommended) supports unique jobs. The job uses:
- 3 retry attempts with backoff: 5s, 15s, 30s
- 10-second unique lock window per subscriber per day

## Event System

The package fires `DigestScheduled` events with three listeners that run as a pipeline:
1. Generate a tracking reference ID
2. Calculate optimal delivery window
3. Assign priority based on volume

These are registered automatically by the service provider.
