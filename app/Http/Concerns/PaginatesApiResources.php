<?php

namespace App\Http\Concerns;

trait PaginatesApiResources
{
    protected function paginate($query, array $validated)
    {
        $perPage = $validated['per_page'] ?? 15;

        if (($validated['pagination'] ?? 'offset') === 'cursor') {
            return $query->cursorPaginate($perPage);
        }

        return $query->paginate($perPage);
    }
}
