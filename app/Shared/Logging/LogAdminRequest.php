<?php

namespace App\Shared\Logging;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class LogAdminRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $response = $next($request);

        if (! $request->isMethodSafe() || $response->getStatusCode() >= 400) {
            $context = [
                'module' => $this->moduleName($request),
                'action' => $request->route()?->getName(),
                'admin_id' => $request->user()?->getAuthIdentifier(),
                'status' => $response->getStatusCode(),
                'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2),
            ];

            $level = $response->getStatusCode() >= 500 ? 'error' : ($response->getStatusCode() >= 400 ? 'warning' : 'info');
            Log::{$level}('admin.request.completed', $context);
        }

        return $response;
    }

    private function moduleName(Request $request): string
    {
        $routeArea = explode('.', (string) $request->route()?->getName())[1]
            ?? (string) $request->segment(2);

        return match ($routeArea) {
            'products', 'product-categories' => 'Products',
            default => Str::studly($routeArea),
        };
    }
}
