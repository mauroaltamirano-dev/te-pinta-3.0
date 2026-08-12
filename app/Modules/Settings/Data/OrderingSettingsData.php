<?php

namespace App\Modules\Settings\Data;

use Illuminate\Foundation\Http\FormRequest;

final readonly class OrderingSettingsData
{
    /**
     * @param  array{unit: int, half_dozen: int, dozen: int}  $cookingSurcharges
     * @param  list<array{id: int, name: string, price: int, is_available: bool, sort_order: int}>  $addons
     */
    public function __construct(
        public int $combinedDozenRawPrice,
        public int $combinedDozenCookedPrice,
        public int $deliveryFee,
        public string $whatsappOrderPhone,
        public array $cookingSurcharges,
        public array $addons,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        $surcharges = $request->safe()->array('cooking_surcharges');
        $addons = $request->safe()->array('addons');

        return new self(
            combinedDozenRawPrice: $request->integer('combined_dozen_raw_price'),
            combinedDozenCookedPrice: $request->integer('combined_dozen_cooked_price'),
            deliveryFee: $request->integer('delivery_fee'),
            whatsappOrderPhone: trim($request->string('whatsapp_order_phone')->toString()),
            cookingSurcharges: [
                'unit' => (int) $surcharges['unit'],
                'half_dozen' => (int) $surcharges['half_dozen'],
                'dozen' => (int) $surcharges['dozen'],
            ],
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
