<?php

namespace App\Modules\Orders\Pricing;

use App\Modules\Orders\Enums\DiscountType;
use InvalidArgumentException;

/** Exact quote arithmetic; every numerator uses the stated denominator. */
final readonly class ExactOrderTotals
{
    public const MAX_STORED_PESOS = 9_999_999_999;

    private const SCALE = 1200;

    /** Commercial rounding step for the final order total, in pesos. */
    public const ROUNDING_STEP = 100;

    private function __construct(
        public int $eligibleNumerator1200,
        public int $discountNumerator1200,
        public int $netNumerator1200,
        public int $roundingAdjustmentNumerator1200,
        public int $productsSubtotal,
        public int $discountAmount,
        public int $roundingAdjustment,
        public int $total,
    ) {}

    public static function calculate(
        int $productsNumerator12,
        int $addonsSubtotal,
        int $shippingFee,
        ?DiscountType $discountType = null,
        ?int $discountValue = null,
    ): self {
        $limit = self::MAX_STORED_PESOS;
        if ($productsNumerator12 < 0 || $productsNumerator12 > $limit * 12
            || $addonsSubtotal < 0 || $addonsSubtotal > $limit
            || $shippingFee < 0 || $shippingFee > $limit
            || ($discountType === null) !== ($discountValue === null)
            || ($discountValue !== null && $discountValue < 0)
            || ($discountType === DiscountType::Percentage && $discountValue > 100)) {
            throw new InvalidArgumentException('The quote contains an invalid or unrepresentable amount.');
        }

        $eligible = $productsNumerator12 * 100 + $addonsSubtotal * self::SCALE;
        if ($eligible > $limit * self::SCALE) {
            throw new InvalidArgumentException('The discountable subtotal exceeds order storage.');
        }

        $discount = match ($discountType) {
            DiscountType::Percentage => intdiv($eligible * $discountValue, 100),
            DiscountType::FixedAmount => $discountValue >= intdiv($eligible + self::SCALE - 1, self::SCALE)
                ? $eligible : $discountValue * self::SCALE,
            null => 0,
        };
        $net = $eligible - $discount + $shippingFee * self::SCALE;
        if ($net > $limit * self::SCALE) {
            throw new InvalidArgumentException('The net amount exceeds order storage.');
        }

        // The only commercial rounding: once, half-up, to the nearest ROUNDING_STEP pesos.
        $step = self::SCALE * self::ROUNDING_STEP;
        $total = intdiv($net + intdiv($step, 2), $step) * self::ROUNDING_STEP;
        if ($total > $limit) {
            throw new InvalidArgumentException('The rounded total exceeds order storage.');
        }

        // Legacy whole-peso projections reconcile, but are never pricing inputs.
        $productsSubtotal = intdiv($productsNumerator12 + 6, 12);
        $discountAmount = intdiv($discount + 600, self::SCALE);
        $roundingAdjustment = $total - ($productsSubtotal + $addonsSubtotal - $discountAmount + $shippingFee);

        return new self(
            eligibleNumerator1200: $eligible,
            discountNumerator1200: $discount,
            netNumerator1200: $net,
            roundingAdjustmentNumerator1200: $total * self::SCALE - $net,
            productsSubtotal: $productsSubtotal,
            discountAmount: $discountAmount,
            roundingAdjustment: $roundingAdjustment,
            total: $total,
        );
    }
}
