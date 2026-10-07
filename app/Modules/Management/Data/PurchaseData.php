<?php

namespace App\Modules\Management\Data;

use App\Modules\Management\Enums\PurchasePriceMode;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final readonly class PurchaseData
{
    /** @param list<PurchaseLineData> $lines */
    public function __construct(
        public string $idempotencyKey,
        public int $walletId,
        public CarbonImmutable $effectiveDate,
        public ?string $supplierName,
        public ?string $description,
        public int $shippingAmount,
        public array $lines,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            idempotencyKey: (string) $request->validated('idempotency_key'),
            walletId: $request->integer('wallet_id'),
            effectiveDate: CarbonImmutable::parse($request->validated('effective_date'))->startOfDay(),
            supplierName: self::nullableTrim($request->validated('supplier_name')),
            description: self::nullableTrim($request->validated('description')),
            shippingAmount: (int) ($request->validated('shipping_amount') ?? 0),
            lines: array_values(array_map(static fn (array $line): PurchaseLineData => new PurchaseLineData(
                inventoryItemId: (int) $line['inventory_item_id'],
                purchasePresentationId: isset($line['purchase_presentation_id']) ? (int) $line['purchase_presentation_id'] : null,
                inputUnitId: isset($line['input_unit_id']) ? (int) $line['input_unit_id'] : null,
                quantity: (string) $line['quantity'],
                priceMode: PurchasePriceMode::from($line['price_mode']),
                priceAmount: (int) $line['price_amount'],
            ), $request->validated('lines'))),
        );
    }

    private static function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
