<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Modules\Settings\Actions\UpdatePublicOrderingHours;
use App\Modules\Settings\Http\Requests\UpdatePublicOrderingHoursRequest;
use Illuminate\Http\RedirectResponse;

final class PublicOrderingHoursSettingsController
{
    public function update(UpdatePublicOrderingHoursRequest $request, UpdatePublicOrderingHours $updateHours): RedirectResponse
    {
        $updateHours->execute($request->validated('hours'));

        return to_route('admin.settings.general.index')->with('success', 'Horario de pedidos web actualizado.');
    }
}
