<?php

namespace App\Models;

use App\Events\NotificationCreated;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory, BelongsToProject;

    protected $fillable = [
        'uuid',
        'project_id',
        'subscriber_id',
        'alert_rule_id',
        'channel',
        'subject',
        'body',
        'payload',
        'status',
        'sent_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Notification $notification): void {
            $notification->uuid ??= (string) Str::uuid();
        });

        static::created(function (Notification $notification): void {
            NotificationCreated::dispatch($notification);
        });
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    public function alertRule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class);
    }
}
