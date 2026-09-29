<?php

declare(strict_types=1);

use App\Modules\Marketing\Queries\BuildFeaturedProductPublicView;
use App\Modules\Products\Http\Resources\PublicProductResource;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Support\PublicProductImageUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

$loader = require '/var/www/html/vendor/autoload.php';
$loader->setClassMapAuthoritative(true);

if (! class_exists(PublicProductImageUrl::class)) {
    throw new RuntimeException('The public image helper is unavailable with classmap-authoritative autoloading.');
}

if (PublicProductImageUrl::fromPath('products/overlay-image.PNG') !== '/storage/products/overlay-image.PNG') {
    throw new RuntimeException('The public image helper did not return the expected same-origin URL.');
}

if (PublicProductImageUrl::fromPath('products/../private.png') !== null) {
    throw new RuntimeException('The public image helper accepted an unsafe path.');
}

$product = new Product([
    'name' => 'Caprese',
    'slug' => 'caprese',
    'description' => 'Tomato and basil.',
    'image_path' => 'products/overlay-image.PNG',
    'unit_price' => 1600,
    'half_dozen_price' => 9000,
    'dozen_price' => 16000,
]);
$product->setRelation('category', new Category(['slug' => 'classic', 'name' => 'Classic']));
$product->setRelation('tags', new Collection);

$catalog = (new PublicProductResource($product))->resolve(Request::create('/menu/catalog'));
$featured = (new BuildFeaturedProductPublicView)->product($product);

if ($catalog['image_url'] !== '/storage/products/overlay-image.PNG' || $featured['image_url'] !== '/storage/products/overlay-image.PNG') {
    throw new RuntimeException('The patched public serializers did not return same-origin image URLs.');
}
