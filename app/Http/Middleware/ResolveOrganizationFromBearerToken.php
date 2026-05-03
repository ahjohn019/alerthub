<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveOrganizationFromBearerToken
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        $token = $request->bearerToken();

        if (! $token) {
            return $this->unauthorized('Missing bearer token.');
        }

        $organization = Organization::query()
            ->where('api_token', $token)
            ->first();

        if (! $organization) {
            return $this->unauthorized('Invalid bearer token.');
        }

        $this->tenant->setOrganization($organization);
        $request->attributes->set('organization', $organization);

        return $next($request);
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'errors' => [],
        ], Response::HTTP_UNAUTHORIZED);
    }
}
