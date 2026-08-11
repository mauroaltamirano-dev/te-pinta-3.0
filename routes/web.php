<?php

use Illuminate\Support\Facades\Route;

require base_path('app/Modules/PublicSite/routes.php');

Route::middleware(['auth', 'verified', 'admin', 'admin.request.log'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        require base_path('app/Modules/Dashboard/routes.php');
        require base_path('app/Modules/Orders/routes.php');
        require base_path('app/Modules/Customers/routes.php');
        require base_path('app/Modules/Products/routes.php');
        require base_path('app/Modules/Analytics/routes.php');
        require base_path('app/Modules/Inventory/routes.php');
        require base_path('app/Modules/Settings/routes.php');
    });
