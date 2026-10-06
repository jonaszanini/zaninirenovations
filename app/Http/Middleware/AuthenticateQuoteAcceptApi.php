<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthenticateQuoteAcceptApi
{
    public function handle(Request $request, Closure $next)
    {
        $configured = (string) config('quote_accept.api_token');
        if ($configured === '') {
            return response()->json(['status' => 'unauthorized'], 401);
        }

        $provided = (string) $request->bearerToken();
        if ($provided === '' || ! hash_equals($configured, $provided)) {
            return response()->json(['status' => 'unauthorized'], 401);
        }

        return $next($request);
    }
}
