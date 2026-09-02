<?php

namespace App\Modules\Management\Actions;

use App\Modules\Management\Models\FinancialTransaction;
use App\Modules\Management\Models\ManagementAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class StoreManagementAttachment
{
    public function execute(FinancialTransaction $transaction, UploadedFile $file, int $adminId): ManagementAttachment
    {
        $source = $file->getRealPath();
        if ($source === false) {
            throw new RuntimeException('No se pudo leer el comprobante subido.');
        }

        $diskName = 'local';
        $path = 'management/attachments/'.now()->format('Y/m').'/'.Str::uuid();
        $checksum = hash_file('sha256', $source);
        $mime = $file->getMimeType();
        if (! is_string($checksum) || ! is_string($mime)) {
            throw new RuntimeException('No se pudo validar el comprobante subido.');
        }

        $stream = fopen($source, 'rb');
        if ($stream === false || ! Storage::disk($diskName)->put($path, $stream)) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            throw new RuntimeException('No se pudo guardar el comprobante.');
        }
        fclose($stream);

        try {
            $attachment = DB::transaction(fn (): ManagementAttachment => ManagementAttachment::query()->create([
                'financial_transaction_id' => $transaction->id,
                'disk' => $diskName,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $mime,
                'size' => $file->getSize(),
                'checksum_sha256' => $checksum,
                'uploaded_by' => $adminId,
            ]));
        } catch (Throwable $exception) {
            Storage::disk($diskName)->delete($path);

            throw $exception;
        }

        Log::info('[Management] attachment.uploaded', [
            'module' => 'Management',
            'management_attachment_id' => $attachment->id,
            'financial_transaction_id' => $transaction->id,
            'admin_id' => $adminId,
            'mime_type' => $mime,
            'size' => $attachment->size,
        ]);

        return $attachment->load('uploader');
    }
}
