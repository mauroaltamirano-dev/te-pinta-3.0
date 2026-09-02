<?php

namespace App\Modules\Management\Http\Resources;

use App\Modules\Management\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Wallet */
final class AdminWalletResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sort_order' => (int) $this->sort_order,
            'is_order_payment_default' => $this->is_order_payment_default,
            'archived_at' => $this->archived_at?->toIso8601String(),
            'balance' => (int) ($this->balance ?? 0),
        ];
    }
}
