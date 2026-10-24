<?php

namespace App\Modules\Settings\Data;

use Illuminate\Foundation\Http\FormRequest;

final readonly class OrderingSettingsData
{
    /**
     * @param  list<array{id: int, name: string, price: int, is_available: bool, sort_order: int}>  $addons
     */
    public function __construct(
        public int $empanadaUnitSurchargePercent,
        public int $combinedDozenDiscountPercent,
        public int $deliveryFee,
        public string $whatsappOrderPhone,
        public int $rawDiscountPercent,
        public array $addons,
        public ?int $combinedHalfDozenSurcharge = null,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        $addons = $request->safe()->array('addons');

        return new self(
            combinedHalfDozenSurcharge: $request->has('combined_half_dozen_surcharge') ? $request->integer('combined_half_dozen_surcharge') : null,
            empanadaUnitSurchargePercent: $request->integer('empanada_unit_surcharge_percent'),
            combinedDozenDiscountPercent: $request->integer('combined_dozen_discount_percent'),
            deliveryFee: $request->integer('delivery_fee'),
            whatsappOrderPhone: trim($request->string('whatsapp_order_phone')->toString()),
            rawDiscountPercent: $request->integer('raw_discount_percent'),
            addons: array_values(array_map(
                static fn (array $addon): array => [
                    'id' => (int) $addon['id'],
                    'name' => trim((string) $addon['name']),
                    'price' => (int) $addon['price'],
                    'is_available' => (bool) $addon['is_available'],
                    'sort_order' => (int) $addon['sort_order'],
                ],
                $addons,
            )),
        );
    }
}
