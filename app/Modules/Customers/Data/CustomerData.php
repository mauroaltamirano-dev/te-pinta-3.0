<?php

namespace App\Modules\Customers\Data;

use Illuminate\Foundation\Http\FormRequest;

final readonly class CustomerData
{
    public function __construct(
        public string $name,
        public ?string $phone,
        public ?string $phoneNormalized,
        public ?string $address,
        public ?string $notes,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            phone: $request->validated('phone'),
            phoneNormalized: $request->validated('phone_normalized'),
            address: $request->validated('address'),
            notes: $request->validated('notes'),
        );
    }

    /** @return array{name: string, phone: ?string, phone_normalized: ?string, address: ?string, notes: ?string} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'phone' => $this->phone,
            'phone_normalized' => $this->phoneNormalized,
            'address' => $this->address,
            'notes' => $this->notes,
        ];
    }
}
