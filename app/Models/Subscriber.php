<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProject;
use Database\Factories\SubscriberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscriber extends Model
{
    /** @use HasFactory<SubscriberFactory> */
    use HasFactory, BelongsToProject;

    protected $fillable = [
        'project_id',
        'email',
        'external_id',
        'name',
        'notification_count',
        'last_notified_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'last_notified_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }
}
