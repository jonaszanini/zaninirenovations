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
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($request->isMethod('delete') && $user && ! $user->isAdmin()) {
            abort(403, 'Your account does not have permission to delete records. Ask an admin.');
        }

        return $next($request);
    }
}
