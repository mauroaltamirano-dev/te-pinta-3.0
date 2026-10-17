<?php

namespace App\Modules\Products\Http\Controllers;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Products\Pricing\HomogeneousHalfDozenPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class HalfDozenPricePreviewController
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can(Permission::CatalogManage->value) === true, 403);
        $data = $request->validate([
            'dozen_price' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'homogeneous_half_dozen_surcharge' => ['required', 'regex:/\A[0-9]{1,10}(?:\.[05])?\z/'],
        ]);
        try {
            $half = HomogeneousHalfDozenPrice::fromSurcharge((int) $data['dozen_price'], (string) $data['homogeneous_half_dozen_surcharge']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['homogeneous_half_dozen_surcharge' => $exception->getMessage()]);
        }

        return response()->json(['half_dozen_price' => $half]);
    }
}
