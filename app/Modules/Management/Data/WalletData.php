<?php

namespace App\Modules\Management\Data;

use Illuminate\Foundation\Http\FormRequest;

final readonly class WalletData
{
    public function __construct(
        public string $name,
        public int $sortOrder,
        public bool $isOrderPaymentDefault,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            name: trim($request->string('name')->toString()),
            sortOrder: $request->integer('sort_order'),
            isOrderPaymentDefault: $request->boolean('is_order_payment_default'),
        );
    }
}
