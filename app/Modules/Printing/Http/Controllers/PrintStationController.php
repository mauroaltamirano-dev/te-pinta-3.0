<?php

namespace App\Modules\Printing\Http\Controllers;

use App\Core\Tenancy\RestaurantContext;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** The shop PC page that prints the queue; the jobs themselves come from PrintJobController. */
final class PrintStationController
{
    public function __invoke(RestaurantContext $restaurant): Response
    {
        return Inertia::render('admin/printing/station', [
            'restaurant' => DB::table('restaurants')->where('id', $restaurant->id())->value('name'),
        ]);
    }
}
