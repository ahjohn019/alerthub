<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProject;
use Database\Factories\WebhookSourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookSource extends Model
{
    /** @use HasFactory<WebhookSourceFactory> */
    use HasFactory, BelongsToProject;

    protected $fillable = [
        'project_id',
        'source_key',
        'source_type',
        'name',
        'signing_secret',
        'event_mappings',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'event_mappings' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
