<?php

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admits only users with an active membership in the current restaurant.
 * Each route then requires its own `can:<permission>`.
 */
final class EnsureActiveMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->role() !== null, 403);

        return $next($request);
    }
}
