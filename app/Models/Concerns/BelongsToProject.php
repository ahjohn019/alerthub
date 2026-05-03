<?php

namespace App\Models\Concerns;

use App\Models\Project;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToProject
{
    protected static function bootBelongsToProject(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $organizationId = app(TenantContext::class)->organizationId();

            if ($organizationId) {
                $builder->whereHas('project', function (Builder $query) use ($organizationId): void {
                    $query->where('organization_id', $organizationId);
                });
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->whereHas('project', function (Builder $projectQuery) use ($organizationId): void {
            $projectQuery->where('organization_id', $organizationId);
        });
    }
}
