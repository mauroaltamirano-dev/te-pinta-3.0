<?php

namespace App\Modules\Restaurant\Http\Controllers;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Restaurant\Actions\CreateDiningArea;
use App\Modules\Restaurant\Actions\UpdateDiningAreaLayout;
use App\Modules\Restaurant\Http\Requests\CreateDiningAreaRequest;
use App\Modules\Restaurant\Http\Requests\UpdateDiningAreaLayoutRequest;
use App\Modules\Restaurant\Models\DiningArea;
use App\Modules\Restaurant\Queries\FloorLayoutQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class FloorLayoutController
{
    public function index(FloorLayoutQuery $query): Response
    {
        Gate::authorize(Permission::OrdersManage->value);

        return Inertia::render('admin/restaurant/floor', ['areas' => $query->areas()]);
    }

    public function store(CreateDiningAreaRequest $request, CreateDiningArea $action, FloorLayoutQuery $query): JsonResponse
    {
        return response()->json(['area' => $query->area($action->execute($request->validated()))], 201);
    }

    public function update(UpdateDiningAreaLayoutRequest $request, DiningArea $area, UpdateDiningAreaLayout $action, FloorLayoutQuery $query): JsonResponse
    {
        try {
            $saved = $action->execute($area, $request->validated());
        } catch (ConflictHttpException $exception) {
            return response()->json(['code' => 'layout_conflict', 'message' => $exception->getMessage()], 409);
        }

        return response()->json(['area' => $query->area($saved)]);
    }
}
