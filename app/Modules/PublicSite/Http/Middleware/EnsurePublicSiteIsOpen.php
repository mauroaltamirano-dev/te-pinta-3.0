<?php

namespace App\Modules\PublicSite\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Closes the storefront while the public site shows the coming-soon screen. */
final class EnsurePublicSiteIsOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('public_site.open')) {
            return $next($request);
        }

        abort_unless($request->isMethod('GET') && ! $request->expectsJson(), 404);

        return redirect('/');
    }
}
