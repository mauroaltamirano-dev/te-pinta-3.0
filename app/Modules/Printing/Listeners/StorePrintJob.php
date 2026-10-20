<?php

namespace App\Modules\Printing\Listeners;

use App\Modules\Orders\Events\PrintableDocumentIssued;
use Illuminate\Support\Facades\DB;

/** Persists an issued document in the same transaction; a repeated business event stores nothing. */
final class StorePrintJob
{
    public function handle(PrintableDocumentIssued $event): void
    {
        DB::table('print_jobs')->insertOrIgnore([
            'kind' => $event->kind,
            'order_id' => $event->orderId,
            'dedupe_key' => $event->dedupeKey,
            'payload' => json_encode($event->payload, JSON_THROW_ON_ERROR),
            'requested_by' => $event->actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
