<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\Identity\Console\CreateAdministrator;
use App\Modules\Identity\Http\Middleware\EnsureActiveMembership;
use App\Modules\Instagram\Console\FailStaleInstagramStoryPublicationsCommand;
use App\Modules\Instagram\Console\GenerateInstagramStoryPublications;
use App\Modules\Instagram\Http\Middleware\AuthenticateN8nAutomation;
use App\Shared\Logging\AssignRequestId;
use App\Shared\Logging\LogAdminRequest;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Env;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = Env::get('TRUSTED_PROXIES');
        $middleware->trustProxies(at: is_string($trustedProxies) ? $trustedProxies : null);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            AssignRequestId::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'restaurant.member' => EnsureActiveMembership::class,
            'admin.request.log' => LogAdminRequest::class,
            'n8n.automation' => AuthenticateN8nAutomation::class,
        ]);
    })
    ->withCommands([CreateAdministrator::class, GenerateInstagramStoryPublications::class, FailStaleInstagramStoryPublicationsCommand::class])
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // PHP discards a body over post_max_size before routing, so no field or
        // limit can be named. Inertia visits get JSON too: the client shows it as a toast.
        $exceptions->render(function (PostTooLargeException $exception, Request $request) {
            $message = 'El archivo es demasiado grande.';

            return $request->expectsJson() || $request->hasHeader('X-Inertia')
                ? response()->json(['message' => $message], 413)
                : response($message, 413);
        });
    })->create();
