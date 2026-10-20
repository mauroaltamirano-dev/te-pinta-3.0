<?php

namespace App\Modules\Orders\Events;

/**
 * A kitchen ticket or bill to print. The payload is a frozen, self-contained snapshot;
 * the dedupe key makes the same business event produce at most one print job.
 */
final readonly class PrintableDocumentIssued
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $kind,
        public string $dedupeKey,
        public int $orderId,
        public array $payload,
        public int $actorId,
    ) {}
}
