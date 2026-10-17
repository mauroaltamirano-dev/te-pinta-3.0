<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Core\Branding\RestaurantBranding;
use App\Modules\Settings\Http\Requests\UpdateBrandingRequest;
use Illuminate\Http\RedirectResponse;

final class BrandingSettingsController
{
    public function update(UpdateBrandingRequest $request, RestaurantBranding $branding): RedirectResponse
    {
        $branding->update($request->validated('accent_color'));

        return to_route('admin.settings.general.index')->with('success', 'Color de acento actualizado.');
    }
}
