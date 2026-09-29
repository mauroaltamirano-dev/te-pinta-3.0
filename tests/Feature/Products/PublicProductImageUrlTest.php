<?php

namespace Tests\Feature\Products;

use App\Modules\Products\Http\Resources\PublicProductResource;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PublicProductImageUrlTest extends TestCase
{
    public function test_public_product_image_url_is_same_origin_despite_private_app_url(): void
    {
        config([
            'app.url' => 'https://tuki-notebook.tail2b1906.ts.net',
            'filesystems.disks.public.url' => 'https://tuki-notebook.tail2b1906.ts.net/storage',
        ]);

        $product = $this->product('products/Empanada-01.PNG');

        $data = (new PublicProductResource($product))->resolve(Request::create('/menu/catalog'));

        $this->assertSame('/storage/products/Empanada-01.PNG', $data['image_url']);
        $this->assertSame('Caprese', $data['name']);
        $this->assertSame(1600, $data['prices']['unit']);
    }

    #[DataProvider('unsafeImagePaths')]
    public function test_public_product_image_url_hides_unsafe_stored_paths(?string $imagePath): void
    {
        $product = $this->product($imagePath);

        $data = (new PublicProductResource($product))->resolve(Request::create('/menu/catalog'));

        $this->assertNull($data['image_url']);
    }

    /** @return array<string, array{0: string|null}> */
    public static function unsafeImagePaths(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'traversal' => ['products/../private.png'],
            'double slash' => ['products//image.png'],
            'query' => ['products/image.png?download=1'],
            'fragment' => ['products/image.png#preview'],
            'backslash' => ['products\\image.png'],
            'encoded slash' => ['products%2Fimage.png'],
            'encoded dot' => ['products/%2e%2e/image.png'],
            'unsupported extension' => ['products/image.svg'],
            'external URL' => ['https://attacker.invalid/image.png'],
            'protocol relative URL' => ['//attacker.invalid/image.png'],
            'trailing newline' => ["products/image.png\nattacker"],
        ];
    }

    private function product(?string $imagePath): Product
    {
        $product = new Product([
            'name' => 'Caprese',
            'slug' => 'caprese',
            'description' => 'Tomate y albahaca.',
            'image_path' => $imagePath,
            'unit_price' => 1600,
            'half_dozen_price' => 9000,
            'dozen_price' => 16000,
        ]);
        $product->setRelation('category', new Category(['slug' => 'clasicas', 'name' => 'Clásicas']));
        $product->setRelation('tags', new Collection);

        return $product;
    }
}
