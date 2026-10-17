<?php

namespace App\Providers;

use App\Core\Branding\RestaurantBranding;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

final class BrandingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Resolve lazily: console boot and migrations must not query restaurant branding.
        View::composer('app', function (ViewInstance $view): void {
            $view->with('brandPalette', $this->app->make(RestaurantBranding::class)->palette());
        });
    }
}
