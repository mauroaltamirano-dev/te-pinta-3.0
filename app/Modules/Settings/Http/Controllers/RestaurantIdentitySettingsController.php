<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Core\Branding\RestaurantIdentity;
use App\Modules\Settings\Http\Requests\UpdateRestaurantIdentityRequest;
use Illuminate\Http\RedirectResponse;

final class RestaurantIdentitySettingsController
{
    public function update(UpdateRestaurantIdentityRequest $request, RestaurantIdentity $identity): RedirectResponse
    {
        $identity->update($request->identity());

        return to_route('admin.settings.general.index')->with('success', 'Identidad del restaurante actualizada.');
    }
}
