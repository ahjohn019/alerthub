<?php

namespace App\Http\Concerns;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

trait ParsesIncludes
{
    /**
     * @param  array<string, string>  $allowed
     * @return list<string>
     */
    protected function includes(Request $request, array $allowed): array
    {
        $requested = collect(explode(',', (string) $request->query('includes')))
            ->map(fn (string $include): string => trim($include))
            ->filter()
            ->unique()
            ->values();

        if ($requested->diff(array_keys($allowed))->isNotEmpty()) {
            throw ValidationException::withMessages([
                'includes' => ['One or more requested includes are not supported.'],
            ])->status(400);
        }

        return $requested
            ->map(fn (string $include): string => $allowed[$include])
            ->all();
    }
}
