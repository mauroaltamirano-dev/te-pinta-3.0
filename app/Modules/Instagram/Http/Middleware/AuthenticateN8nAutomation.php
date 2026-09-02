<?php

namespace App\Modules\Instagram\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateN8nAutomation
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = config('instagram.n8n_token');
        $provided = $request->bearerToken();

        if (! is_string($configured) || $configured === '' || ! is_string($provided) || ! hash_equals($configured, $provided)) {
            abort(401, 'Unauthorized.');
        }

        return $next($request);
    }
}
