<?php

namespace App\Modules\Management\Http\Resources;

use App\Modules\Management\Models\ManagementAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ManagementAttachment */
final class ManagementAttachmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $url = route('admin.management.transactions.attachments.show', [
            'financialTransaction' => $this->financial_transaction_id,
            'managementAttachment' => $this->id,
        ]);

        return [
            'id' => $this->id,
            'name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'created_at' => $this->created_at?->toIso8601String(),
            'uploaded_by' => [
                'id' => $this->uploader->id,
                'name' => $this->uploader->name,
            ],
            'preview_url' => in_array($this->mime_type, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)
                ? $url.'?disposition=inline'
                : null,
            'download_url' => $url.'?disposition=attachment',
        ];
    }
}
