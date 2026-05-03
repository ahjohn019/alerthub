<?php

namespace App\Services;

use App\Models\Subscriber;

class SubscriberResolver
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function resolve(int $projectId, array $payload): Subscriber
    {
        $attributes = $this->subscriberAttributes($payload);
        $lookup = [];

        if (filled($attributes['email'] ?? null)) {
            $lookup['email'] = $attributes['email'];
        } elseif (filled($attributes['external_id'] ?? null)) {
            $lookup['external_id'] = $attributes['external_id'];
        }

        return Subscriber::withoutGlobalScopes()->updateOrCreate(
            ['project_id' => $projectId, ...$lookup],
            [
                'name' => $attributes['name'] ?? null,
                'email' => $attributes['email'] ?? null,
                'external_id' => $attributes['external_id'] ?? null,
                'metadata' => $attributes['metadata'] ?? [],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function subscriberAttributes(array $payload): array
    {
        $email = data_get($payload, 'payload.contact.email')
            ?? data_get($payload, 'payload.customer.email')
            ?? data_get($payload, 'payload.sender.email')
            ?? data_get($payload, 'payload.commits.0.author.email');

        $externalId = data_get($payload, 'payload.contact.external_id')
            ?? data_get($payload, 'payload.customer.id')
            ?? data_get($payload, 'payload.sender.login')
            ?? data_get($payload, 'payload.alert_id');

        return [
            'email' => $email,
            'external_id' => $externalId,
            'name' => data_get($payload, 'payload.contact.name')
                ?? data_get($payload, 'payload.sender.login')
                ?? data_get($payload, 'payload.commits.0.author.name'),
            'metadata' => [
                'source' => data_get($payload, 'source'),
                'event_type' => data_get($payload, 'event_type'),
            ],
        ];
    }
}
