# AlertHub — Senior Laravel Developer Assignment

## Overview

AlertHub is a **multi-tenant alert management platform**. Organizations receive webhook events from external services (GitHub, Stripe, monitoring tools), process them through configurable rules, and deliver notifications to subscribers.

This assignment evaluates your ability to build a production-grade Laravel application with proper architecture, integrate a legacy module, and demonstrate investigative debugging skills.

| | |
|---|---|
| **Duration** | 6 -- 8 hours |
| **Deliverables** | GitHub repository + `README.md` + `BUG_REPORT.md` + working demo |

---

## Domain & Data Model

The platform is organized around **Organizations** (tenants) that contain **Projects**, which in turn scope all other entities.

```
Organization (tenant)
  - id, uuid, name, api_token, plan (free/pro/enterprise), timezone
  - created_at, updated_at, deleted_at

Project (scoped unit within an org)
  - id, uuid, organization_id, name, description
  - created_at, updated_at, deleted_at

Subscriber (alert recipient within a project)
  - id, project_id, email, external_id, name
  - notification_count, last_notified_at, metadata (json)
  - created_at, updated_at

AlertRule (conditions + actions, scoped to project)
  - id, project_id, name
  - source_type (github/stripe/monitoring/custom)
  - event_type, conditions (json)
  - action (notify/escalate/digest)
  - priority (low/medium/high/critical)
  - is_active
  - created_at, updated_at

Notification (sent alert log)
  - id, uuid, project_id, subscriber_id, alert_rule_id
  - channel (email/webhook), subject, body, payload (json)
  - status (pending/sent/failed/escalated)
  - sent_at
  - created_at, updated_at

WebhookSource (vendor config for a project)
  - id, project_id, source_key (unique per project)
  - source_type (github/stripe/monitoring/custom)
  - name, signing_secret, event_mappings (json)
  - is_active
  - created_at, updated_at
```

### Entity Relationship Diagram

```
Organization
  └── Project
        ├── Subscriber
        ├── AlertRule
        ├── Notification
        └── WebhookSource
```

All project-child entities are strictly scoped: a Subscriber, AlertRule, Notification, or WebhookSource always belongs to exactly one Project.

---

## Requirements

### A. Multi-Tenant Foundation (~1 hour)

Build the data layer and tenant isolation mechanism.

- Create **models, migrations, factories, and seeders** for all six entities listed above
- Implement **tenant-scoping middleware**: resolve the `Organization` from a `Bearer` API token in the `Authorization` header, and scope all downstream queries to that organization
- Implement **project-level scoping**: all project-child models (Subscriber, AlertRule, Notification, WebhookSource) must be scoped to the authenticated organization's projects
- **Seeders** should create at least **2 organizations** with multiple projects each, populated with sample data

### B. REST API (~1 hour)

All endpoints require an `Authorization: Bearer {org_api_token}` header.

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/projects` | List the organization's projects |
| `POST` | `/api/projects` | Create a new project |
| `GET` | `/api/projects/{id}` | Get project detail |
| `PUT` | `/api/projects/{id}` | Update a project |
| `GET` | `/api/projects/{id}/subscribers` | List subscribers (paginated) |
| `POST` | `/api/projects/{id}/subscribers` | Create a subscriber |
| `GET` | `/api/projects/{id}/notifications` | List notifications (filterable) |
| `POST` | `/api/projects/{id}/alert-rules` | Create an alert rule |
| `GET` | `/api/projects/{id}/alert-rules` | List alert rules |
| `POST` | `/api/projects/{id}/webhook-sources` | Register a webhook source |

#### Resource System

Implement an **includable relation system** where the client can request related resources via query parameter:

```
GET /api/projects/{id}?includes=subscribers,alert_rules
```

Resource classes should **conditionally include relations only when loaded** -- do not eager-load everything by default.

#### Pagination

All list endpoints must support **cursor or offset pagination**.

#### Error Format

Return consistent JSON error responses:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "name": ["The name field is required."]
  }
}
```

Use appropriate HTTP status codes (`400`, `401`, `404`, `422`, etc.).

### C. Webhook Processing Endpoint (~30 min)

```
POST /api/webhooks/{project_uuid}/{source_key}
```

- This endpoint is **public** -- no auth token required. It uses the `project_uuid` and `source_key` for identification.
- Validate that the webhook source exists and is active
- Optionally verify the request signature using `signing_secret` if one is configured
- Dispatch a `ProcessWebhookEvent` job to the queue
- Return **`202 Accepted`** immediately

### D. Alert Processing Pipeline -- Chain of Responsibility (~1 hour)

The `ProcessWebhookEvent` job runs the incoming event through a **handler chain**. Each handler in the chain returns one of three states:

| State | Behavior |
|-------|----------|
| `CONTINUE` | Pass to the next handler in the chain |
| `QUIT` | Stop processing entirely |
| `SKIP_TO_DISPATCH` | Skip remaining handlers, go directly to notification dispatch |

#### Handler Chain

Implement the following handlers **in this order**:

1. **`DeduplicationHandler`** -- Cache-based duplicate check using an event hash. If the event is a duplicate within a 5-minute window, return `QUIT`.

2. **`ValidationHandler`** -- Validate the payload structure based on the source type. Invalid payloads return `QUIT` with a logged reason.

3. **`SubscriberMatchHandler`** -- Find or create a subscriber from the event payload (by email or external_id). Wire the provided `SubscriberResolver` from the legacy module here.

4. **`RuleEvaluationHandler`** -- Match active alert rules to the event based on `source_type`, `event_type`, and `conditions`. If no rules match, return `QUIT`.

5. **`NotificationDispatchHandler`** -- Create a `Notification` record and dispatch a `SendNotification` job for async delivery.

#### Architecture

Create a **base `Handler` abstract class** and a **`Pipeline` runner** that manages the chain. The pipeline should be easy to extend with new handlers.

### E. Event-Driven Side Effects (~30 min)

- Fire a `NotificationCreated` event when a Notification is saved
- **`UpdateSubscriberStats` listener**: increment the subscriber's `notification_count` and update `last_notified_at`
- **`CheckEscalation` listener**: if the subscriber has received more than N notifications in a time window, mark the notification status as `escalated`
- Listeners must execute in the **correct order**: stats update **BEFORE** escalation check (the escalation logic reads the updated count)

### F. Queue Jobs (~30 min)

#### `ProcessWebhookEvent`

- Implements `ShouldQueue`
- Accepts the webhook payload and source information
- Runs the alert processing pipeline (Section D)

#### `SendNotification`

- Implements `ShouldQueue`
- Handles email/webhook delivery with retry logic

#### Both jobs should:

- Implement `ShouldBeUnique` with appropriate `uniqueId()` methods
- Configure sensible `$tries` and `$backoff` values
- Implement a `failed()` method for error handling

---

## Legacy Module Integration (~1 hour)

You are provided a pre-built **AlertMetrics** package in the `/legacy-package/` directory. This was written by a previous developer and needs to be integrated into your application.

### Integration Steps

Refer to the package's own README for API details. At a high level:

1. **Register `MetricsServiceProvider`** in your application's service providers
2. **Wire `SubscriberResolver`** into your pipeline's `SubscriberMatchHandler`
3. **Wire `MetricsAggregator`** to track alert counts on notification creation
4. **Wire `DigestScheduler`** for batched alert digests
5. **Wire `EngagementScorer`** into subscriber API responses

### Known Issues Backlog

The QA team has filed the following bug reports against the legacy AlertMetrics module. After integrating the module, investigate each ticket, find the root cause, apply a fix, and document your findings.

For each ticket, add your resolution to a `BUG_REPORT.md` file:

---

**AH-101** | Metrics dashboard shows wrong numbers
- **Reporter**: QA Team
- **Priority**: High
- **Description**: The daily alert count on the metrics dashboard doesn't match what we see in the database. When we check project "Acme Payments", the count includes alerts from other projects too. Refreshing the page sometimes shows different numbers.
- **Steps to Reproduce**: Create alerts in two different projects on the same day. Check the daily count for one project — it shows the total across both.

---

**AH-102** | Webhooks from monitoring tools sometimes create duplicate subscribers or fail silently
- **Reporter**: Support Team
- **Priority**: Critical
- **Description**: Customers report that monitoring webhooks (which don't always include an email address) sometimes create duplicate subscriber entries. Other times the webhook just doesn't create a subscriber at all and the alert is lost. This seems to happen more during incident spikes when many webhooks arrive at once.
- **Steps to Reproduce**: Send multiple monitoring webhooks simultaneously for a contact that only has an `external_id` (no email). Observe duplicate subscribers or missing alerts.

---

**AH-103** | Alert digests are never scheduled with a delivery window
- **Reporter**: Product Manager
- **Priority**: Medium
- **Description**: We added the digest scheduling feature but digests are always sent without a delivery window — the `scheduled_window` field is always empty. The priority assignment works fine though. We expected high-volume digests to be marked as "immediate" and low-volume ones as "next_batch".
- **Steps to Reproduce**: Trigger the digest scheduler for a project with pending notifications. Check the DigestScheduled event — `scheduledWindow` is always null.

---

**AH-104** | During incidents, only the first alert digest is processed per subscriber
- **Reporter**: QA Team
- **Priority**: High
- **Description**: When a subscriber receives multiple alerts in quick succession (e.g., during a service outage), only the first digest job actually runs. Subsequent alerts for the same subscriber on the same day seem to be silently dropped within about 10 seconds of each other.
- **Steps to Reproduce**: Dispatch 3 digest jobs for the same subscriber within 10 seconds, each with different alert IDs. Only the first one executes.

---

**AH-105** | Subscriber engagement scores are inconsistent between API and digest emails
- **Reporter**: QA Team
- **Priority**: Medium
- **Description**: A subscriber's engagement score shown in the API response is different from the score used in their digest email. It seems like the score changes depending on which feature accessed it last. If we run the daily digest batch and then check the API, the API shows the digest score instead of the realtime score.
- **Steps to Reproduce**: Check a subscriber's engagement score via the API (should be realtime). Then run the digest scheduler. Check the API again — the score has changed to the digest value.

---

#### Resolution Format

For each ticket, document in `BUG_REPORT.md`:

| Field | Description |
|-------|-------------|
| **Ticket** | The ticket ID (e.g., AH-101) |
| **Root Cause** | Technical explanation — what's wrong in the code (reference file and line) |
| **Fix Applied** | What you changed and why |
| **Regression Test** | Describe or include the test that verifies the fix |
| **Prevention** | How to prevent this class of bug in the future |

---

## Sample Webhook Payloads

Use these payloads for testing your webhook endpoint and processing pipeline.

### GitHub Push Event

```json
{
  "event_type": "push",
  "source": "github",
  "payload": {
    "ref": "refs/heads/main",
    "commits": [
      {
        "id": "abc123",
        "message": "Deploy hotfix for payment processing",
        "author": {
          "name": "Jane Dev",
          "email": "jane@example.com"
        },
        "timestamp": "2024-01-15T10:30:00Z"
      }
    ],
    "repository": {
      "full_name": "acme/payment-service"
    },
    "sender": {
      "login": "janedev",
      "email": "jane@example.com"
    }
  }
}
```

### Stripe Payment Failed

```json
{
  "event_type": "payment_intent.payment_failed",
  "source": "stripe",
  "payload": {
    "id": "pi_3abc123",
    "amount": 5000,
    "currency": "usd",
    "customer": {
      "id": "cus_xyz",
      "email": "customer@example.com"
    },
    "last_payment_error": {
      "code": "card_declined",
      "message": "Your card was declined."
    },
    "metadata": {
      "order_id": "ORD-12345"
    }
  }
}
```

### Generic Monitoring Alert

```json
{
  "event_type": "alert.triggered",
  "source": "monitoring",
  "payload": {
    "alert_id": "mon-789",
    "severity": "critical",
    "service": "api-gateway",
    "message": "Response time exceeded 5s threshold",
    "metric_value": 7.2,
    "threshold": 5.0,
    "triggered_at": "2024-01-15T10:30:00Z",
    "contact": {
      "email": "ops-team@example.com",
      "external_id": "ops-team"
    }
  }
}
```

---

## Time Allocation Guide

| Phase | Estimated Time | Focus |
|-------|---------------|-------|
| Multi-tenant foundation & models | ~1 hour | Solid base with proper scoping |
| REST API & resources | ~1 hour | Clean API design |
| Webhook endpoint & pipeline | ~1.5 hours | Chain of Responsibility pattern |
| Events, listeners, queue jobs | ~30 min | Event-driven architecture |
| Legacy module integration | ~1 hour | Clean integration |
| Bug discovery & documentation | ~1.5 -- 2 hours | Investigation & fixes |
| Tests & polish | remaining | Quality assurance |

> These are rough guides, not rigid constraints. Allocate time based on your strengths.

---

## Deliverable Checklist

Before submitting, verify that your repository includes:

- [ ] Laravel application with all **models, migrations, factories, and seeders**
- [ ] **Multi-tenant middleware** with organization-scoped queries
- [ ] **REST API** with resource transformation and includable relations
- [ ] **Webhook processing endpoint** with async job dispatch
- [ ] **Alert processing pipeline** using the Chain of Responsibility pattern
- [ ] **Event system** with correctly ordered listeners
- [ ] **Queue jobs** with uniqueness constraints and retry handling
- [ ] **AlertMetrics module** integrated with bug fixes applied
- [ ] **`BUG_REPORT.md`** documenting each discovered bug
- [ ] **`README.md`** with setup instructions, architecture overview, API documentation, and design decisions
- [ ] **PHPUnit tests** covering the pipeline, webhook flow, and bug fixes
- [ ] **Database seeders** creating 2+ organizations with multiple projects
- [ ] **Working demo flow**: create org -> create project -> register webhook source -> receive webhook -> trace to notification

---

## Evaluation Criteria

Your submission will be evaluated on the following dimensions:

| Criterion | What We Look For |
|-----------|-----------------|
| **Architecture & Design** | Multi-tenant isolation, pipeline pattern implementation, event system design, queue job design |
| **Bug Investigation** | Thoroughness of discovery, root cause analysis quality, fix correctness |
| **API Design** | RESTful conventions, resource transformation, consistent error handling |
| **Code Quality** | Laravel conventions, test coverage, project organization |
| **Integration Quality** | Clean wiring of the legacy module without hacking its internals |

---

Good luck!
