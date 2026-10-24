<?php

namespace App\Http\Middleware;

use App\Core\Branding\RestaurantBranding;
use App\Core\Tenancy\RestaurantContext;
use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $user = $user instanceof User ? $user : null;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'brand' => function (): array {
                $palette = app(RestaurantBranding::class)->palette();

                return ['accent' => $palette->accent, 'variables' => $palette->cssVariables()];
            },
            'auth' => [
                'user' => $user,
                'role' => $user?->role()?->value,
                'permissions' => $user?->permissionNames() ?? [],
            ],
            // Printed on tickets by the shop PC from any admin page (features/printing).
            'restaurantName' => fn (): ?string => $user?->can('orders.manage')
                ? DB::table('restaurants')->where('id', app(RestaurantContext::class)->id())->value('name')
                : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
