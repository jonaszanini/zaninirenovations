<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Basic RBAC gate: DELETE requests (and any request explicitly flagged as a
 * destructive action, e.g. a POST-based "force delete" button) are blocked
 * for non-admin accounts. Applied at the route-group level so no individual
 * controller needs to remember to check this — one place to audit/extend.
 */
class RestrictDestructiveActions
{
    /** POST routes that delete records without using HTTP DELETE. */
    private const DESTRUCTIVE_POST_ROUTES = [
        'admin.materials.bulk-destroy',
        'admin.materials.config.delete-failed',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isAdmin() && $this->isDestructive($request)) {
            abort(403, 'Your account does not have permission to delete records. Ask an admin.');
        }

        return $next($request);
    }

    private function isDestructive(Request $request): bool
    {
        if ($request->isMethod('delete')) {
            return true;
        }

        if (! $request->isMethod('post')) {
            return false;
        }

        $name = $request->route()?->getName();

        return is_string($name) && in_array($name, self::DESTRUCTIVE_POST_ROUTES, true);
    }
}
