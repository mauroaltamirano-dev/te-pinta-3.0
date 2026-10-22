<?php

namespace Tests\Feature\Management;

use App\Modules\Identity\Models\User;
use App\Modules\Management\Actions\CreateManualTransaction;
use App\Modules\Management\Actions\ReverseFinancialTransaction;
use App\Modules\Management\Actions\StoreManagementAttachment;
use App\Modules\Management\Data\ManualTransactionData;
use App\Modules\Management\Data\ReversalData;
use App\Modules\Management\Enums\FinancialTransactionType;
use App\Modules\Management\Models\FinancialTransaction;
use App\Modules\Management\Models\ManagementAttachment;
use App\Modules\Management\Models\Wallet;
use App\Modules\Management\Queries\BuildManagementOverview;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

final class ManagementAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private FinancialTransaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        CarbonImmutable::setTestNow('2026-08-29 12:00:00');
        Storage::fake('local');
        $this->admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Caja']);
        $this->transaction = app(CreateManualTransaction::class)->execute(new ManualTransactionData(
            $wallet->id,
            FinancialTransactionType::Opening,
            25_000,
            CarbonImmutable::parse('2026-08-29'),
            'Opening',
        ), $this->admin->id);
    }

    public function test_admin_uploads_private_evidence_with_safe_metadata_without_changing_finances(): void
    {
        $ledgerBefore = DB::table('ledger_entries')->get()->map(fn (object $entry): array => (array) $entry)->all();
        $balanceBefore = (int) DB::table('ledger_entries')->sum('amount');

        $response = $this->actingAs($this->admin)
            ->postJson($this->collectionUrl(), ['file' => $this->pdf('factura-000123.pdf')])
            ->assertCreated()
            ->assertJsonPath('attachment.name', 'factura-000123.pdf')
            ->assertJsonPath('attachment.mime_type', 'application/pdf')
            ->assertJsonPath('attachment.uploaded_by.id', $this->admin->id)
            ->assertJsonMissingPath('attachment.path')
            ->assertJsonMissingPath('attachment.disk')
            ->assertJsonMissingPath('attachment.checksum');

        $attachment = ManagementAttachment::query()->sole();
        $this->assertSame($this->transaction->id, $attachment->financial_transaction_id);
        $this->assertSame('local', $attachment->disk);
        $this->assertMatchesRegularExpression(
            '#^management/attachments/2026/08/[0-9a-f-]{36}$#',
            $attachment->path,
        );
        $this->assertSame(hash('sha256', $this->pdfContents()), $attachment->checksum_sha256);
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertStringNotContainsString('factura-000123.pdf', $attachment->path);
        $this->assertDatabaseCount('financial_transactions', 1);
        $this->assertDatabaseCount('ledger_entries', 1);
        $this->assertSame($balanceBefore, (int) DB::table('ledger_entries')->sum('amount'));
        $this->assertSame($ledgerBefore, DB::table('ledger_entries')->get()->map(fn (object $entry): array => (array) $entry)->all());
        $response->assertJsonPath('attachment.download_url', route(
            'admin.management.transactions.attachments.show',
            [$this->transaction, $attachment],
        ).'?disposition=attachment');
    }

    public function test_only_admins_can_list_upload_read_or_delete_attachments(): void
    {
        $attachment = $this->storeAttachment();
        $itemUrl = $this->itemUrl($attachment);

        $this->getJson($this->collectionUrl())->assertUnauthorized();
        $this->postJson($this->collectionUrl(), ['file' => $this->pdf('invoice.pdf')])->assertUnauthorized();
        $this->get($itemUrl.'?disposition=inline')->assertRedirect('/login');
        $this->deleteJson($itemUrl, ['reason' => 'Wrong document'])->assertUnauthorized();

        $user = User::factory()->create();
        $this->actingAs($user)->getJson($this->collectionUrl())->assertForbidden();
        $this->actingAs($user)->postJson($this->collectionUrl(), ['file' => $this->pdf('invoice.pdf')])->assertForbidden();
        $this->actingAs($user)->get($itemUrl.'?disposition=inline')->assertForbidden();
        $this->actingAs($user)->deleteJson($itemUrl, ['reason' => 'Wrong document'])->assertForbidden();
    }

    public function test_validation_rejects_unsupported_spoofed_oversized_and_malicious_files(): void
    {
        $files = [
            UploadedFile::fake()->createWithContent('payload.html', '<html><script>alert(1)</script></html>'),
            UploadedFile::fake()->createWithContent('payload.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
            UploadedFile::fake()->createWithContent('invoice.jpg', $this->pdfContents()),
            UploadedFile::fake()->createWithContent('invoice.pdf.exe', $this->pdfContents()),
            UploadedFile::fake()->createWithContent(str_repeat('a', 252).'.pdf', $this->pdfContents()),
            UploadedFile::fake()->create('huge.pdf', 10 * 1024 + 1, 'application/pdf'),
        ];

        foreach ($files as $file) {
            $this->actingAs($this->admin)
                ->postJson($this->collectionUrl(), ['file' => $file])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('file');
        }

        $this->assertDatabaseCount('management_attachments', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('management/attachments'));
    }

    public function test_file_over_the_php_upload_limit_reports_a_translated_validation_error(): void
    {
        // An 11 MB file exceeds upload_max_filesize (10M), so PHP hands over an errored upload.
        $path = tempnam(sys_get_temp_dir(), 'attachment');
        file_put_contents($path, $this->pdfContents());
        $file = new UploadedFile($path, 'factura-11mb.pdf', 'application/pdf', UPLOAD_ERR_INI_SIZE, true);

        try {
            $this->actingAs($this->admin)
                ->postJson($this->collectionUrl(), ['file' => $file])
                ->assertUnprocessable()
                ->assertExactJson([
                    'message' => 'El archivo adjunto no se pudo subir.',
                    'errors' => ['file' => ['El archivo adjunto no se pudo subir.']],
                ]);
        } finally {
            @unlink($path);
        }

        $this->assertDatabaseCount('management_attachments', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('management/attachments'));
    }

    public function test_supported_image_formats_are_validated_from_their_real_contents(): void
    {
        foreach ([
            'photo.jpg' => 'image/jpeg',
            'scan.png' => 'image/png',
            'receipt.webp' => 'image/webp',
        ] as $name => $mime) {
            $this->actingAs($this->admin)
                ->postJson($this->collectionUrl(), ['file' => UploadedFile::fake()->image($name, 2, 2)])
                ->assertCreated()
                ->assertJsonPath('attachment.mime_type', $mime);
        }

        $this->assertSame(
            ['image/jpeg', 'image/png', 'image/webp'],
            ManagementAttachment::query()->orderBy('id')->pluck('mime_type')->all(),
        );
    }

    public function test_multiple_attachments_are_listed_as_metadata_and_overview_uses_only_a_count(): void
    {
        $first = $this->storeAttachment('first.pdf');
        CarbonImmutable::setTestNow('2026-08-29 12:01:00');
        $second = $this->storeAttachment('second.pdf');

        $this->actingAs($this->admin)
            ->getJson($this->collectionUrl())
            ->assertOk()
            ->assertJsonCount(2, 'attachments')
            ->assertJsonPath('attachments.0.id', $second->id)
            ->assertJsonPath('attachments.1.id', $first->id)
            ->assertJsonMissingPath('attachments.0.path');

        $overview = app(BuildManagementOverview::class)->execute([
            'wallet_id' => null, 'type' => null, 'category_id' => null,
            'from' => '2026-08-01', 'to' => '2026-08-31',
        ]);
        $transaction = $overview['transactions']->items()[0];
        $this->assertInstanceOf(FinancialTransaction::class, $transaction);
        $this->assertSame(2, $transaction->attachments_count);
        $this->assertFalse($transaction->relationLoaded('attachments'));

        $this->actingAs($this->admin)
            ->get('/admin/management?from=2026-08-01&to=2026-08-31')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('transactions.data.0.attachments_count', 2));
    }

    public function test_authorized_inline_and_download_verify_integrity_and_block_idor(): void
    {
        $attachment = $this->storeAttachment();
        $itemUrl = $this->itemUrl($attachment);

        $this->actingAs($this->admin)
            ->get($itemUrl.'?disposition=inline')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertHeader('cache-control', 'no-store, private');
        $this->actingAs($this->admin)
            ->get($itemUrl.'?disposition=attachment')
            ->assertOk()
            ->assertDownload('invoice.pdf');
        $this->actingAs($this->admin)
            ->get($itemUrl.'?disposition=invalid')
            ->assertSessionHasErrors('disposition');

        $other = $this->otherTransaction();
        $this->actingAs($this->admin)
            ->get("/admin/management/transactions/{$other->id}/attachments/{$attachment->id}?disposition=inline")
            ->assertNotFound();

        Storage::disk('local')->put($attachment->path, 'corrupt');
        $this->actingAs($this->admin)->get($itemUrl.'?disposition=inline')->assertNotFound();
        Storage::disk('local')->delete($attachment->path);
        $this->actingAs($this->admin)->get($itemUrl.'?disposition=inline')->assertNotFound();
    }

    public function test_delete_creates_an_audited_tombstone_but_retains_private_bytes(): void
    {
        $attachment = $this->storeAttachment();
        $events = [];
        Log::listen(function (MessageLogged $event) use (&$events): void {
            $events[] = $event;
        });

        $this->actingAs($this->admin)
            ->deleteJson($this->itemUrl($attachment), ['reason' => 'Incorrect supporting document'])
            ->assertNoContent();

        $deleted = ManagementAttachment::withTrashed()->findOrFail($attachment->id);
        $this->assertNotNull($deleted->deleted_at);
        $this->assertSame($this->admin->id, $deleted->deleted_by);
        $this->assertSame('Incorrect supporting document', $deleted->deletion_reason);
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertDatabaseCount('financial_transactions', 1);
        $this->assertDatabaseCount('ledger_entries', 1);
        $this->actingAs($this->admin)->getJson($this->collectionUrl())->assertJsonCount(0, 'attachments');
        $this->actingAs($this->admin)->get($this->itemUrl($attachment))->assertNotFound();

        $this->assertTrue(collect($events)->contains(fn (MessageLogged $event): bool => $event->level === 'info'
            && $event->message === '[Management] attachment.deleted'
            && $event->context['management_attachment_id'] === $attachment->id
            && $event->context['financial_transaction_id'] === $this->transaction->id
            && ! array_key_exists('path', $event->context)
        ));
    }

    public function test_reversal_keeps_original_attachment_and_does_not_copy_it(): void
    {
        $attachment = $this->storeAttachment();
        $reversal = app(ReverseFinancialTransaction::class)->execute(
            $this->transaction,
            new ReversalData(CarbonImmutable::parse('2026-08-29'), 'Correction'),
            $this->admin->id,
        );

        $this->assertSame(1, $this->transaction->attachments()->count());
        $this->assertSame(0, $reversal->attachments()->count());
        $this->assertSame($this->transaction->id, $attachment->fresh()->financial_transaction_id);
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertDatabaseCount('management_attachments', 1);
    }

    public function test_storage_file_is_cleaned_up_when_database_creation_fails(): void
    {
        Event::listen('eloquent.creating: '.ManagementAttachment::class, static function (): never {
            throw new RuntimeException('Forced database failure');
        });

        try {
            app(StoreManagementAttachment::class)->execute(
                $this->transaction,
                $this->pdf('invoice.pdf'),
                $this->admin->id,
            );
            $this->fail('Expected attachment persistence to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced database failure', $exception->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.ManagementAttachment::class);
        }

        $this->assertDatabaseCount('management_attachments', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('management/attachments'));
    }

    public function test_postgresql_rejects_metadata_changes_physical_deletion_and_invalid_storage_rows(): void
    {
        $attachment = $this->storeAttachment();

        foreach ([
            fn () => DB::table('management_attachments')->where('id', $attachment->id)->update(['original_name' => 'changed.pdf']),
            fn () => DB::table('management_attachments')->where('id', $attachment->id)->delete(),
            fn () => DB::table('management_attachments')->insert([
                'financial_transaction_id' => $this->transaction->id,
                'disk' => 'public',
                'path' => '../../public/invoice.pdf',
                'original_name' => 'invoice.pdf',
                'mime_type' => 'application/pdf',
                'size' => 10,
                'checksum_sha256' => str_repeat('a', 64),
                'uploaded_by' => $this->admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]),
        ] as $mutation) {
            try {
                DB::transaction($mutation);
                $this->fail('Expected attachment evidence constraints to reject the mutation.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function storeAttachment(string $name = 'invoice.pdf'): ManagementAttachment
    {
        return app(StoreManagementAttachment::class)->execute(
            $this->transaction,
            $this->pdf($name),
            $this->admin->id,
        );
    }

    private function otherTransaction(): FinancialTransaction
    {
        $wallet = Wallet::query()->create(['name' => 'Banco']);

        return app(CreateManualTransaction::class)->execute(new ManualTransactionData(
            $wallet->id,
            FinancialTransactionType::Opening,
            1000,
            CarbonImmutable::parse('2026-08-29'),
            'Other opening',
        ), $this->admin->id);
    }

    private function collectionUrl(): string
    {
        return "/admin/management/transactions/{$this->transaction->id}/attachments";
    }

    private function itemUrl(ManagementAttachment $attachment): string
    {
        return $this->collectionUrl().'/'.$attachment->id;
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $this->pdfContents());
    }

    private function pdfContents(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<<>>\n%%EOF\n";
    }
}
