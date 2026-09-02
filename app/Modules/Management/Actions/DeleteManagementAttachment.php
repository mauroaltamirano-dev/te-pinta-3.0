<?php

namespace App\Modules\Management\Actions;

use App\Modules\Management\Models\ManagementAttachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class DeleteManagementAttachment
{
    public function execute(ManagementAttachment $attachment, string $reason, int $adminId): void
    {
        DB::transaction(function () use ($attachment, $reason, $adminId): void {
            $locked = ManagementAttachment::query()->lockForUpdate()->findOrFail($attachment->id);
            $now = now();
            ManagementAttachment::query()->whereKey($locked->id)->update([
                'deleted_at' => $now,
                'deleted_by' => $adminId,
                'deletion_reason' => $reason,
                'updated_at' => $now,
            ]);
        });

        Log::info('[Management] attachment.deleted', [
            'module' => 'Management',
            'management_attachment_id' => $attachment->id,
            'financial_transaction_id' => $attachment->financial_transaction_id,
            'admin_id' => $adminId,
            'reason' => $reason,
        ]);
    }
}
