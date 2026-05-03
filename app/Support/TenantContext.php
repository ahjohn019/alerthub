<?php

namespace App\Support;

use App\Models\Organization;

class TenantContext
{
    public function __construct(
        private ?Organization $organization = null,
    ) {}

    public function setOrganization(?Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function organization(): ?Organization
    {
        return $this->organization;
    }

    public function organizationId(): ?int
    {
        return $this->organization?->id;
    }
}
