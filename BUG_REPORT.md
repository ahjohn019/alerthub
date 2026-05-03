# Bug Report

## AH-101
- **Ticket**: AH-101
- **Root Cause**: `app/AlertMetrics/src/MetricsAggregator.php` counted all `Notification` rows for a date without filtering by `project_id`, and it reused global cache keys like `alert-metrics::{date}` so one project could overwrite another project’s cached count.
- **Fix Applied**: Scoped all metrics queries and cache keys by `project_id`, and updated `DigestScheduler::getDigestStats()` to pass the project ID through.
- **Regression Test**: `tests/Unit/AlertMetricsIntegrationTest.php::test_metrics_aggregator_scopes_counts_by_project`
- **Prevention**: Keep every tenant-aware metric query and cache key project-scoped, and prefer regression tests that create data in multiple projects on the same date.

## AH-102
- **Ticket**: AH-102
- **Root Cause**: `app/AlertMetrics/src/SubscriberResolver.php` only searched by email, used an email-only cache lock key, and skipped `external_id`-only payloads during webhook spikes.
- **Fix Applied**: Added `external_id`-aware lookup and lock keys, and stored metadata as an array so the resolver can correctly persist monitoring payloads without an email address.
- **Regression Test**: `tests/Unit/AlertMetricsIntegrationTest.php::test_subscriber_resolver_uses_external_id_when_email_is_missing`
- **Prevention**: Treat webhook identity as a multi-field match, and test payloads that omit email entirely.

## AH-103
- **Ticket**: AH-103
- **Root Cause**: The digest event listener pipeline in `app/AlertMetrics/src/MetricsServiceProvider.php` was registered in the wrong order, so `CalculateDigestWindow` could run before `GenerateDigestId` and exit early.
- **Fix Applied**: Registered the listeners in the intended order: generate ID, then calculate the window, then assign priority.
- **Regression Test**: `tests/Unit/AlertMetricsIntegrationTest.php::test_digest_scheduled_listeners_populate_window_before_priority`
- **Prevention**: Keep ordered event pipelines covered by tests that assert the final event state, not just listener registration.

## AH-104
- **Ticket**: AH-104
- **Root Cause**: `app/AlertMetrics/src/ProcessAlertDigest.php` used a uniqueness key based only on subscriber and date, so distinct digest batches for the same subscriber on the same day were treated as duplicates.
- **Fix Applied**: Expanded `uniqueId()` to include a hash of the digest type and alert ID batch.
- **Regression Test**: `tests/Unit/AlertMetricsIntegrationTest.php::test_process_alert_digest_unique_id_includes_alert_batch`
- **Prevention**: Include the digest payload in uniqueness decisions whenever the job is meant to dedupe only identical work.

## AH-105
- **Ticket**: AH-105
- **Root Cause**: `app/AlertMetrics/src/EngagementScorer.php` cached scores under a subscriber-only key, so a digest score could be reused by the realtime API path and vice versa.
- **Fix Applied**: Namespaced the cache key by both subscriber ID and scoring context.
- **Regression Test**: `tests/Unit/AlertMetricsIntegrationTest.php::test_engagement_scores_do_not_bleed_between_contexts`
- **Prevention**: Cache context-sensitive computations with a context-sensitive key, and test both call orders.
