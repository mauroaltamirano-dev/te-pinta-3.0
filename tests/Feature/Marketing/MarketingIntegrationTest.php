<?php

namespace Tests\Feature\Marketing;

use App\Modules\Identity\Models\User;
use App\Modules\Marketing\Models\FeaturedProduct;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class MarketingIntegrationTest extends TestCase
{
    use InteractsWithMarketing;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-08-23 15:00:00 UTC'));
    }

    public function test_admin_page_is_authorized_and_exposes_safe_management_shapes(): void
    {
        $product = $this->product();
        $this->featured($product);

        $this->get(route('admin.marketing.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.marketing.index'))->assertForbidden();

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('admin.marketing.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/marketing/index')
                ->where('business_timezone', 'America/Argentina/Buenos_Aires')
                ->where('form_defaults.starts_at', '2026-08-23T12:00')
                ->where('form_defaults.ends_at', null)
                ->where('form_defaults.is_enabled', true)
                ->where('current.status', 'active')
                ->where('current.starts_at', '2026-08-23T14:00:00+00:00')
                ->where('current.starts_at_local', '2026-08-23T11:00')
                ->where('current.is_publicly_visible', true)
                ->where('current.visibility_reason', null)
                ->where('current.public_view.product.slug', $product->slug)
                ->where('current.public_view.product.prices.unit', 1600)
                ->has('upcoming', 0)
                ->has('history', 0)
                ->has('selectable_products', 1));
    }

    public function test_admin_upcoming_records_are_serialized_as_a_contiguous_json_array(): void
    {
        $currentProduct = $this->product();
        $upcomingProduct = $this->product(null, ['name' => 'Caprese']);
        $this->featured($currentProduct);
        $this->featured($upcomingProduct, [
            'starts_at' => '2026-08-24 14:00:00+00',
            'ends_at' => '2026-08-24 20:00:00+00',
        ]);

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('admin.marketing.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('upcoming', 1)
                ->where('upcoming.0.product.name', 'Caprese'));
    }

    public function test_public_home_only_receives_current_sellable_featured_product(): void
    {
        $product = $this->product();
        $category = $product->category;
        $featured = $this->featured($product);

        $this->get(route('home'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/home')
                ->where('featuredProduct', [
                    'headline' => 'Nuestra recomendación de hoy',
                    'short_copy' => 'Un clásico del norte.',
                    'product' => [
                        'slug' => $product->slug,
                        'name' => 'Salteña',
                        'description' => 'Carne cortada a cuchillo.',
                        'image_url' => Storage::disk('public')->url('products/saltena.jpg'),
                        'prices' => [
                            'unit' => 1600,
                            'half_dozen' => 9000,
                            'dozen' => 16000,
                        ],
                    ],
                ]));

        $product->update(['is_available' => false]);
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('featuredProduct', null));

        $product->update(['is_available' => true]);
        $product->delete();
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('featuredProduct', null));

        $product->restore();
        $category->update(['is_active' => false]);
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('featuredProduct', null));

        $category->update(['is_active' => true]);
        $category->delete();
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('featuredProduct', null));

        $category->restore();
        $featured->update(['is_enabled' => false]);
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('featuredProduct', null));
    }

    public function test_future_expired_and_inactive_features_are_not_public(): void
    {
        $product = $this->product();

        foreach ([
            ['starts_at' => '2026-08-23 16:00:00+00', 'ends_at' => '2026-08-23 17:00:00+00', 'is_enabled' => true],
            ['starts_at' => '2026-08-23 13:00:00+00', 'ends_at' => '2026-08-23 15:00:00+00', 'is_enabled' => true],
            ['starts_at' => '2026-08-23 14:00:00+00', 'ends_at' => '2026-08-23 16:00:00+00', 'is_enabled' => false],
        ] as $window) {
            $feature = $this->featured($product, $window);
            $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('featuredProduct', null));
            $feature->delete();
        }
    }

    public function test_selectable_products_exclude_every_unsellable_catalog_state(): void
    {
        $availableCategory = $this->category();
        $inactiveCategory = $this->category(['is_active' => false]);
        $deletedCategory = $this->category();
        $available = $this->product($availableCategory, ['name' => 'Disponible']);
        $this->product($availableCategory, ['name' => 'No disponible', 'is_available' => false]);
        $this->product($inactiveCategory, ['name' => 'Categoría inactiva']);
        $this->product($deletedCategory, ['name' => 'Categoría eliminada']);
        $deletedCategory->delete();
        $deletedProduct = $this->product($availableCategory, ['name' => 'Eliminado']);
        $deletedProduct->delete();

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('admin.marketing.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('selectable_products', 1)
                ->where('selectable_products.0.id', $available->id)
                ->where('selectable_products.0.product.name', 'Disponible'));
    }

    public function test_guest_and_non_admin_cannot_write_and_no_delete_route_exists(): void
    {
        $product = $this->product();
        $featured = $this->featured($product);
        $payload = $this->payload($product);

        $this->post(route('admin.marketing.featured-products.store'), $payload)->assertRedirect(route('login'));
        $this->patch(route('admin.marketing.featured-products.update', $featured), $payload)->assertRedirect(route('login'));
        $this->patch(route('admin.marketing.featured-products.disable', $featured))->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)->post(route('admin.marketing.featured-products.store'), $payload)->assertForbidden();
        $this->actingAs($user)->patch(route('admin.marketing.featured-products.update', $featured), $payload)->assertForbidden();
        $this->actingAs($user)->patch(route('admin.marketing.featured-products.disable', $featured))->assertForbidden();

        $this->assertNull(Route::getRoutes()->getByName('admin.marketing.featured-products.destroy'));
        $this->actingAs(User::factory()->administrator()->create())
            ->delete("/admin/marketing/featured-products/{$featured->id}")
            ->assertMethodNotAllowed();
        $this->assertDatabaseHas('marketing_featured_products', ['id' => $featured->id]);
    }

    public function test_dashboard_summarizes_active_hidden_upcoming_and_empty_states(): void
    {
        $admin = User::factory()->administrator()->create();
        $product = $this->product();
        $featured = $this->featured($product);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('marketingSummary.state', 'active')
                ->where('marketingSummary.product_name', 'Salteña')
                ->where('marketingSummary.business_timezone', 'America/Argentina/Buenos_Aires'));

        $product->update(['is_available' => false]);
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('marketingSummary.state', 'active_hidden')
                ->where('marketingSummary.visibility_reason', 'product_unavailable'));

        $featured->update([
            'starts_at' => '2026-08-24 14:00:00+00',
            'ends_at' => '2026-08-24 20:00:00+00',
        ]);
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('marketingSummary.state', 'upcoming')
                ->where('marketingSummary.starts_at', '2026-08-24T14:00:00+00:00'));

        $featured->update(['is_enabled' => false]);
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('marketingSummary.state', 'none')
                ->where('marketingSummary.product_name', null));
    }

    public function test_update_disable_and_logs_preserve_history_without_delete_route(): void
    {
        Log::spy();
        $admin = User::factory()->administrator()->create();
        $product = $this->product();

        $this->actingAs($admin)->post(route('admin.marketing.featured-products.store'), $this->payload($product))
            ->assertSessionHasNoErrors();
        $featured = FeaturedProduct::query()->sole();

        $this->actingAs($admin)->patch(route('admin.marketing.featured-products.update', $featured), $this->payload($product, [
            'headline' => 'Elegida por Te Pinta',
        ]))->assertSessionHasNoErrors();

        $this->actingAs($admin)->patch(route('admin.marketing.featured-products.disable', $featured))
            ->assertSessionHasNoErrors();

        $this->assertFalse($featured->refresh()->is_enabled);
        $this->assertDatabaseCount('marketing_featured_products', 1);
        // @phpstan-ignore staticMethod.notFound
        Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === '[Marketing] featured_product.created'
            && $context['featured_product_id'] === $featured->id
            && ! array_key_exists('headline', $context))->once();
        // @phpstan-ignore staticMethod.notFound
        Log::shouldHaveReceived('info')->withArgs(fn (string $message): bool => $message === '[Marketing] featured_product.updated')->once();
        // @phpstan-ignore staticMethod.notFound
        Log::shouldHaveReceived('info')->withArgs(fn (string $message): bool => $message === '[Marketing] featured_product.disabled')->once();
    }
}
