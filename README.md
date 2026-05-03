# AlertHub

AlertHub is a multi-tenant Laravel alert management API. Organizations own projects, projects receive webhook events, and matched alert rules create queued notifications for subscribers.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan queue:work
```

For this local Laragon workspace, PHPUnit uses a MySQL database named `alerthub_test` because the available PHP binaries do not include `pdo_sqlite`.

```bash
php artisan test
```

## Architecture

- Tenant resolution lives in `ResolveOrganizationFromBearerToken` and stores the active organization in `TenantContext`.
- `Project` is scoped by organization, and project-child models use `BelongsToProject` to enforce organization scoping.
- Webhook processing uses a Chain of Responsibility pipeline: deduplication, payload validation, legacy subscriber resolution, rule evaluation, then notification dispatch.
- Notification side effects are event driven. `UpdateSubscriberStats` runs before `CheckEscalation`.
- Queue jobs use uniqueness, retry/backoff settings, and failure handlers.
- The legacy `AlertMetrics` package is registered through `MetricsServiceProvider`.

## API

All project API endpoints require:

```http
Authorization: Bearer {organization_api_token}
Accept: application/json
```

Seeded demo tokens:

- `acme-alerts-token`
- `globex-monitoring-token`

Endpoints:

- `GET /api/projects`
- `POST /api/projects`
- `GET /api/projects/{id}`
- `PUT /api/projects/{id}`
- `GET /api/projects/{id}/subscribers`
- `POST /api/projects/{id}/subscribers`
- `GET /api/projects/{id}/notifications`
- `GET /api/projects/{id}/alert-rules`
- `POST /api/projects/{id}/alert-rules`
- `POST /api/projects/{id}/webhook-sources`

List endpoints support offset pagination by default and cursor pagination with `?pagination=cursor`. Supported relations can be loaded with `includes`, for example:

```http
GET /api/projects/1?includes=subscribers,alert_rules
```

## API Result

Sample responses based on the actual API resource shapes:

`GET /api/projects?includes=subscribers,alert_rules`

```json
{
  "data": [
    {
      "id": 1,
      "uuid": "2f3c9d8e-7a91-4b8c-9d17-1f7c0e5d1a01",
      "organization_id": 1,
      "name": "Payments API",
      "description": "Payment alerts and integrations",
      "created_at": "2026-05-01T08:15:00.000000Z",
      "updated_at": "2026-05-01T08:15:00.000000Z",
      "subscribers": [
        {
          "id": 10,
          "project_id": 1,
          "email": "ops-team@example.com",
          "external_id": "ops-team",
          "name": "Ops Team",
          "notification_count": 4,
          "last_notified_at": "2026-05-02T08:15:00.000000Z",
          "metadata": {
            "team": "ops"
          },
          "engagement_score": 82.5,
          "engagement_tier": "high",
          "created_at": "2026-05-01T08:15:00.000000Z",
          "updated_at": "2026-05-02T08:15:00.000000Z",
          "notifications": []
        }
      ],
      "alert_rules": [
        {
          "id": 3,
          "project_id": 1,
          "name": "Critical monitoring incidents",
          "source_type": "monitoring",
          "event_type": "alert.triggered",
          "conditions": {
            "severity": "critical"
          },
          "action": "escalate",
          "priority": "critical",
          "is_active": true,
          "created_at": "2026-05-01T08:15:00.000000Z",
          "updated_at": "2026-05-01T08:15:00.000000Z",
          "notifications": []
        }
      ],
      "notifications": [],
      "webhook_sources": []
    }
  ],
  "links": {
    "first": "http://alerthub.test/api/projects?page=1",
    "last": "http://alerthub.test/api/projects?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "path": "http://alerthub.test/api/projects",
    "per_page": 15,
    "to": 1,
    "total": 1
  }
}
```

`GET /api/projects/{id}`

```json
{
  "id": 1,
  "uuid": "2f3c9d8e-7a91-4b8c-9d17-1f7c0e5d1a01",
  "organization_id": 1,
  "name": "Payments API",
  "description": "Payment alerts and integrations",
  "created_at": "2026-05-01T08:15:00.000000Z",
  "updated_at": "2026-05-01T08:15:00.000000Z",
  "subscribers": [],
  "alert_rules": [],
  "notifications": [],
  "webhook_sources": []
}
```

`GET /api/projects/{id}/notifications?includes=subscriber,alert_rule`

```json
{
  "data": [
    {
      "id": 44,
      "uuid": "f2b8af19-5f5e-4c65-8d74-9a3e7f1aa301",
      "project_id": 1,
      "subscriber_id": 10,
      "alert_rule_id": 3,
      "channel": "email",
      "subject": "Critical monitoring incidents",
      "body": "Response time exceeded 5s threshold",
      "payload": {
        "event_type": "alert.triggered",
        "severity": "critical"
      },
      "status": "sent",
      "sent_at": "2026-05-02T08:20:00.000000Z",
      "created_at": "2026-05-02T08:20:00.000000Z",
      "updated_at": "2026-05-02T08:20:00.000000Z",
      "subscriber": {
        "id": 10,
        "project_id": 1,
        "email": "ops-team@example.com",
        "external_id": "ops-team",
        "name": "Ops Team",
        "notification_count": 4,
        "last_notified_at": "2026-05-02T08:15:00.000000Z",
        "metadata": {
          "team": "ops"
        },
        "engagement_score": 82.5,
        "engagement_tier": "high",
        "created_at": "2026-05-01T08:15:00.000000Z",
        "updated_at": "2026-05-02T08:15:00.000000Z",
        "notifications": []
      },
      "alert_rule": {
        "id": 3,
        "project_id": 1,
        "name": "Critical monitoring incidents",
        "source_type": "monitoring",
        "event_type": "alert.triggered",
        "conditions": {
          "severity": "critical"
        },
        "action": "escalate",
        "priority": "critical",
        "is_active": true,
        "created_at": "2026-05-01T08:15:00.000000Z",
        "updated_at": "2026-05-01T08:15:00.000000Z",
        "notifications": []
      }
    }
  ],
  "links": {
    "first": "http://alerthub.test/api/projects/1/notifications?page=1",
    "last": "http://alerthub.test/api/projects/1/notifications?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "path": "http://alerthub.test/api/projects/1/notifications",
    "per_page": 15,
    "to": 1,
    "total": 1
  }
}
```

## Webhooks

The webhook endpoint is public and identifies the destination by project UUID and source key:

```http
POST /api/webhooks/{project_uuid}/{source_key}
```

If a webhook source has a signing secret, send the HMAC SHA-256 signature in `X-AlertHub-Signature` or `X-Hub-Signature-256`.

Example monitoring payload:

```json
{
  "event_type": "alert.triggered",
  "source": "monitoring",
  "payload": {
    "alert_id": "mon-789",
    "severity": "critical",
    "service": "api-gateway",
    "message": "Response time exceeded 5s threshold",
    "contact": {
      "external_id": "ops-team"
    }
  }
}
```

## Digest Scheduling

Digest jobs can be scheduled for a project:

```bash
php artisan alerthub:schedule-digests 1 --date=2026-05-01 --type=daily
```

## Bug Report

Legacy module investigation and fixes for AH-101 through AH-105 are documented in `BUG_REPORT.md`.

## Demo Flow

1. Run `php artisan migrate --seed`.
2. Use `GET /api/projects` with `Authorization: Bearer acme-alerts-token`.
3. Register or use a seeded webhook source for a project.
4. Send `POST /api/webhooks/{project_uuid}/{source_key}`.
5. Run a queue worker and inspect `GET /api/projects/{id}/notifications`.
