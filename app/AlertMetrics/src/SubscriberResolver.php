<?php

namespace AlertMetrics;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SubscriberResolver
{
    /**
     * Resolve a subscriber from the webhook payload.
     *
     * Finds an existing subscriber or creates a new one based on the
     * payload data. Uses cache locking to prevent duplicate creation
     * during concurrent webhook processing.
     *
     * @param  int     $projectId  The project to scope the subscriber to
     * @param  array   $payload    The webhook event payload
     * @return \App\Models\Subscriber|null
     */
    public function resolve(int $projectId, array $payload): ?\App\Models\Subscriber
    {
        $email = $this->extractEmail($payload);
        $externalId = $this->extractExternalId($payload);
        $name = $this->extractName($payload);

        if (!$email && !$externalId) {
            Log::warning('SubscriberResolver: No identifiable information in payload', [
                'project_id' => $projectId,
            ]);
            return null;
        }

        $subscriber = \App\Models\Subscriber::withoutGlobalScopes()
            ->where('project_id', $projectId)
            ->when($email, fn ($query) => $query->where('email', $email))
            ->when(! $email && $externalId, fn ($query) => $query->where('external_id', $externalId))
            ->first();

        if ($subscriber) {
            return $subscriber;
        }

        // Subscriber doesn't exist — create with lock to prevent duplicates
        $lockKey = sprintf(
            'subscriber-lock:%d:%s:%s',
            $projectId,
            $email ?? 'no-email',
            $externalId ?? 'no-external-id'
        );
        $lock = Cache::lock($lockKey, 5);

        if ($lock->get()) {
            try {
                $subscriber = \App\Models\Subscriber::withoutGlobalScopes()
                    ->where('project_id', $projectId)
                    ->when($email, fn ($query) => $query->where('email', $email))
                    ->when(! $email && $externalId, fn ($query) => $query->where('external_id', $externalId))
                    ->first();

                if ($subscriber) {
                    return $subscriber;
                }

                $subscriber = \App\Models\Subscriber::create([
                    'project_id' => $projectId,
                    'email' => $email,
                    'external_id' => $externalId,
                    'name' => $name ?? 'Unknown',
                    'notification_count' => 0,
                    'metadata' => $this->extractMetadata($payload),
                ]);

                Log::info('SubscriberResolver: Created new subscriber', [
                    'project_id' => $projectId,
                    'subscriber_id' => $subscriber->id,
                    'email' => $email,
                ]);

                return $subscriber;
            } finally {
                $lock->release();
            }
        }

        Log::warning('SubscriberResolver: Could not acquire lock for subscriber creation', [
            'project_id' => $projectId,
            'email' => $email,
        ]);

        return null;
    }

    /**
     * Extract email from various payload structures.
     */
    protected function extractEmail(array $payload): ?string
    {
        // GitHub format
        if (isset($payload['payload']['sender']['email'])) {
            return $payload['payload']['sender']['email'];
        }

        // Stripe format
        if (isset($payload['payload']['customer']['email'])) {
            return $payload['payload']['customer']['email'];
        }

        // Generic format
        if (isset($payload['payload']['contact']['email'])) {
            return $payload['payload']['contact']['email'];
        }

        // Direct email field
        if (isset($payload['email'])) {
            return $payload['email'];
        }

        return null;
    }

    /**
     * Extract external ID from various payload structures.
     */
    protected function extractExternalId(array $payload): ?string
    {
        if (isset($payload['payload']['sender']['login'])) {
            return $payload['payload']['sender']['login'];
        }

        if (isset($payload['payload']['customer']['id'])) {
            return $payload['payload']['customer']['id'];
        }

        if (isset($payload['payload']['contact']['external_id'])) {
            return $payload['payload']['contact']['external_id'];
        }

        if (isset($payload['external_id'])) {
            return $payload['external_id'];
        }

        return null;
    }

    /**
     * Extract display name from payload.
     */
    protected function extractName(array $payload): ?string
    {
        if (isset($payload['payload']['commits'][0]['author']['name'])) {
            return $payload['payload']['commits'][0]['author']['name'];
        }

        if (isset($payload['payload']['contact']['name'])) {
            return $payload['payload']['contact']['name'];
        }

        if (isset($payload['name'])) {
            return $payload['name'];
        }

        return null;
    }

    /**
     * Extract metadata from payload for subscriber record.
     */
    protected function extractMetadata(array $payload): array
    {
        return [
            'source' => $payload['source'] ?? 'unknown',
            'first_seen_event' => $payload['event_type'] ?? null,
            'created_via' => 'webhook',
        ];
    }
}
