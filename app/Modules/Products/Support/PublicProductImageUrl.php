<?php

namespace App\Modules\Products\Support;

final class PublicProductImageUrl
{
    private const PRODUCT_IMAGE_PATH = '/\Aproducts\/[A-Za-z0-9][A-Za-z0-9_-]*\.(?:jpe?g|png|webp)\z/iD';

    public static function fromPath(?string $path): ?string
    {
        if ($path === null || preg_match(self::PRODUCT_IMAGE_PATH, $path) !== 1) {
            return null;
        }

        return '/storage/'.$path;
    }
}
