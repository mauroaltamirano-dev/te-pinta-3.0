<?php

namespace Tests\Feature\Customers;

use App\Modules\Customers\Support\PhoneNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PhoneNormalizerTest extends TestCase
{
    /** @return array<string, array{?string, ?string}> */
    public static function phoneCases(): array
    {
        return [
            'null remains null' => [null, null],
            'blank becomes null' => ['  ', null],
            'punctuation and spaces are removed' => [' +54 (9) 381-555 0123 ', '5493815550123'],
            'digits remain stable' => ['3815550123', '3815550123'],
        ];
    }

    #[DataProvider('phoneCases')]
    public function test_phone_normalization_is_centralized_and_deterministic(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, app(PhoneNormalizer::class)->normalize($input));
    }
}
