<?php

namespace Tests\Feature\PublicSite;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** While the storefront is remodeled the public sees one coming-soon screen and cannot order. */
final class PublicSiteComingSoonTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_closed_site_shows_only_the_coming_soon_screen(): void
    {
        config(['public_site.open' => false]);
        OrderSetting::query()->where('key', OrderSettingKey::WhatsappOrderPhone->value)->update(['string_value' => '+54 9 3534 12-3456']);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('public/coming-soon')
            ->where('whatsappUrl', 'https://wa.me/5493534123456')
            ->where('mapsUrl', 'https://maps.app.goo.gl/CoZHPYbs9VHNMH9L6')
            ->missing('featuredProduct'));
    }

    public function test_a_closed_site_hides_the_storefront_and_takes_no_orders(): void
    {
        config(['public_site.open' => false]);

        foreach (['/menu', '/cart', '/menu/empanada-salteña'] as $path) {
            $this->get($path)->assertRedirect('/');
        }
        foreach (['/menu/catalog', '/checkout/options'] as $path) {
            $this->getJson($path)->assertNotFound();
        }
        $this->postJson('/checkout/price-preview', ['items' => []])->assertNotFound();
        $this->postJson('/checkout/orders', ['items' => []])->assertNotFound();
        $this->assertDatabaseCount((new Order)->getTable(), 0);
    }

    public function test_a_closed_site_shows_no_phone_link_without_a_configured_phone(): void
    {
        config(['public_site.open' => false]);
        OrderSetting::query()->where('key', OrderSettingKey::WhatsappOrderPhone->value)->update(['string_value' => '']);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('public/coming-soon')
            ->where('whatsappUrl', null));
    }

    public function test_an_open_site_keeps_the_storefront(): void
    {
        config(['public_site.open' => true]);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('public/home'));
        $this->get('/menu')->assertOk()->assertInertia(fn (Assert $page) => $page->component('public/menu'));
        $this->getJson('/menu/catalog')->assertOk();
    }

    public function test_the_admin_is_not_affected_by_a_closed_site(): void
    {
        config(['public_site.open' => false]);

        $this->actingAs(User::factory()->administrator()->create())->get(route('admin.orders.index'))->assertOk();
    }

    public function test_the_site_is_closed_by_default_only_in_production(): void
    {
        $this->assertFalse($this->openByDefault('production'));
        $this->assertTrue($this->openByDefault('local'));
        $this->assertTrue($this->openByDefault('testing'));
    }

    private function openByDefault(string $environment): bool
    {
        $previous = $_ENV['APP_ENV'] ?? null;
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = $environment;
        unset($_ENV['PUBLIC_SITE_OPEN'], $_SERVER['PUBLIC_SITE_OPEN']);

        try {
            return (require config_path('public_site.php'))['open'];
        } finally {
            $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = $previous;
        }
    }
}
