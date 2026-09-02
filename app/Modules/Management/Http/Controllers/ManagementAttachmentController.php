<?php

namespace App\Modules\Management\Http\Controllers;

use App\Modules\Management\Actions\DeleteManagementAttachment;
use App\Modules\Management\Actions\StoreManagementAttachment;
use App\Modules\Management\Http\Requests\DeleteManagementAttachmentRequest;
use App\Modules\Management\Http\Requests\StoreManagementAttachmentRequest;
use App\Modules\Management\Http\Resources\ManagementAttachmentResource;
use App\Modules\Management\Models\FinancialTransaction;
use App\Modules\Management\Models\ManagementAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ManagementAttachmentController
{
    public function index(Request $request, FinancialTransaction $financialTransaction): JsonResponse
    {
        Gate::authorize('viewAny', [ManagementAttachment::class, $financialTransaction]);
        $attachments = $financialTransaction->attachments()
            ->with('uploader')
            ->latest('created_at')
            ->latest('id')
            ->get();

        return response()->json([
            'attachments' => $attachments
                ->map(fn (ManagementAttachment $attachment): array => (new ManagementAttachmentResource($attachment))->resolve($request))
                ->all(),
        ]);
    }

    public function store(
        StoreManagementAttachmentRequest $request,
        FinancialTransaction $financialTransaction,
        StoreManagementAttachment $store,
    ): JsonResponse {
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);
        $attachment = $store->execute(
            $financialTransaction,
            $file,
            (int) $request->user()->getAuthIdentifier(),
        );

        return response()->json([
            'attachment' => (new ManagementAttachmentResource($attachment))->resolve($request),
        ], 201);
    }

    public function show(
        Request $request,
        FinancialTransaction $financialTransaction,
        ManagementAttachment $managementAttachment,
    ): StreamedResponse {
        $this->assertOwnedBy($managementAttachment, $financialTransaction);
        Gate::authorize('view', $managementAttachment);
        $validated = $request->validate([
            'disposition' => ['nullable', Rule::in(['inline', 'attachment'])],
        ]);
        $disposition = $validated['disposition'] ?? 'inline';

        abort_unless(
            $managementAttachment->disk === 'local'
                && str_starts_with($managementAttachment->path, 'management/attachments/')
                && ! str_contains($managementAttachment->path, '..'),
            404,
        );
        $disk = Storage::disk('local');
        if (! $disk->exists($managementAttachment->path)) {
            Log::warning('[Management] attachment.file_missing', [
                'module' => 'Management',
                'management_attachment_id' => $managementAttachment->id,
                'financial_transaction_id' => $financialTransaction->id,
            ]);
            abort(404);
        }

        $absolutePath = $disk->path($managementAttachment->path);
        $checksum = hash_file('sha256', $absolutePath);
        $size = filesize($absolutePath);
        if (! is_string($checksum)
            || ! is_int($size)
            || $checksum !== $managementAttachment->checksum_sha256
            || $size !== $managementAttachment->size) {
            Log::warning('[Management] attachment.file_corrupt', [
                'module' => 'Management',
                'management_attachment_id' => $managementAttachment->id,
                'financial_transaction_id' => $financialTransaction->id,
            ]);
            abort(404);
        }

        return $disk->response(
            $managementAttachment->path,
            $managementAttachment->original_name,
            [
                'Content-Type' => $managementAttachment->mime_type,
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
            ],
            $disposition,
        );
    }

    public function destroy(
        DeleteManagementAttachmentRequest $request,
        FinancialTransaction $financialTransaction,
        ManagementAttachment $managementAttachment,
        DeleteManagementAttachment $delete,
    ): Response {
        $this->assertOwnedBy($managementAttachment, $financialTransaction);
        $delete->execute(
            $managementAttachment,
            (string) $request->validated('reason'),
            (int) $request->user()->getAuthIdentifier(),
        );

        return response()->noContent();
    }

    private function assertOwnedBy(ManagementAttachment $attachment, FinancialTransaction $transaction): void
    {
        abort_unless($attachment->financial_transaction_id === $transaction->id, 404);
    }
}
