<?php

namespace App\Modules\Printing\Http\Resources;

use App\Modules\Printing\Models\PrintJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PrintJob */
final class PrintJobResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'status' => $this->status,
            'claim_token' => $this->when($this->status === 'claimed', $this->claim_token),
            'reprint' => $this->reprint_of_id !== null,
            'attempts' => $this->attempts,
            'created_at' => $this->created_at->toIso8601String(),
            'claimed_at' => $this->claimed_at?->toIso8601String(),
            'printed_at' => $this->printed_at?->toIso8601String(),
            'payload' => $this->payload,
        ];
    }
}
