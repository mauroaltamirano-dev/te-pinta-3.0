<?php

use Illuminate\Support\Facades\Route;

require base_path('app/Modules/PublicSite/routes.php');
require base_path('app/Modules/Instagram/public-routes.php');

Route::middleware(['auth', 'verified', 'admin', 'admin.request.log'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        require base_path('app/Modules/Dashboard/routes.php');
        require base_path('app/Modules/Orders/routes.php');
        require base_path('app/Modules/Customers/routes.php');
        require base_path('app/Modules/Products/routes.php');
        require base_path('app/Modules/Analytics/routes.php');
        require base_path('app/Modules/Management/routes.php');
        require base_path('app/Modules/Inventory/routes.php');
        require base_path('app/Modules/Settings/routes.php');
        require base_path('app/Modules/Restaurant/routes.php');
        require base_path('app/Modules/Marketing/routes.php');
        require base_path('app/Modules/Instagram/routes.php');
    });
