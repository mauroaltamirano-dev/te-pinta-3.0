<?php

namespace Tests\Feature\Marketing;

use App\Modules\Marketing\Queries\BuildFeaturedProductPublicView;
use App\Modules\Products\Models\Product;
use Tests\TestCase;

final class FeaturedProductImageUrlTest extends TestCase
{
    public function test_featured_product_image_url_is_same_origin_despite_private_app_url(): void
    {
        config([
            'app.url' => 'https://tuki-notebook.tail2b1906.ts.net',
            'filesystems.disks.public.url' => 'https://tuki-notebook.tail2b1906.ts.net/storage',
        ]);

        $data = (new BuildFeaturedProductPublicView)->product($this->product('products/featured.webp'));

        $this->assertSame('/storage/products/featured.webp', $data['image_url']);
        $this->assertSame('Caprese', $data['name']);
        $this->assertSame(16000, $data['prices']['dozen']);
    }

    public function test_featured_product_hides_missing_or_unsafe_image_paths(): void
    {
        $view = new BuildFeaturedProductPublicView;

        $this->assertNull($view->product($this->product(null))['image_url']);
        $this->assertNull($view->product($this->product('products/../private.jpg'))['image_url']);
    }

    private function product(?string $imagePath): Product
    {
        return new Product([
            'name' => 'Caprese',
            'slug' => 'caprese',
            'image_path' => $imagePath,
            'unit_price' => 1600,
            'half_dozen_price' => 9000,
            'dozen_price' => 16000,
        ]);
    }
}
