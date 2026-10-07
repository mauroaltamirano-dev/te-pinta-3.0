<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Actions\CreatePurchasePresentation;
use App\Modules\Inventory\Actions\UpdatePurchasePresentation;
use App\Modules\Inventory\Data\PurchasePresentationData;
use App\Modules\Inventory\Http\Requests\StorePurchasePresentationRequest;
use App\Modules\Inventory\Http\Requests\UpdatePurchasePresentationRequest;
use App\Modules\Inventory\Models\PurchasePresentation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;

final class PurchasePresentationController
{
    public function store(
        StorePurchasePresentationRequest $request,
        CreatePurchasePresentation $createPresentation,
    ): RedirectResponse {
        $createPresentation->execute($this->data($request));

        return to_route('admin.inventory.index')->with('success', 'Presentación de compra creada.');
    }

    public function update(
        UpdatePurchasePresentationRequest $request,
        PurchasePresentation $purchasePresentation,
        UpdatePurchasePresentation $updatePresentation,
    ): RedirectResponse {
        $updatePresentation->execute($purchasePresentation, $this->data($request));

        return to_route('admin.inventory.index')->with('success', 'Presentación de compra actualizada.');
    }

    private function data(FormRequest $request): PurchasePresentationData
    {
        return new PurchasePresentationData(
            inventoryItemId: $request->integer('inventory_item_id'),
            name: $request->string('name')->toString(),
            contentQuantity: $request->string('content_quantity')->toString(),
            contentUnitId: $request->integer('content_unit_id'),
            isActive: $request->boolean('is_active'),
        );
    }
}
