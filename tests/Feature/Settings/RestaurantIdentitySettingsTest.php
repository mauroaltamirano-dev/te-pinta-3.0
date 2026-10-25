<?php

namespace Tests\Feature\Settings;

use App\Core\Tenancy\RestaurantContext;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\RawPriceDiscount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class RestaurantIdentitySettingsTest extends TestCase
{
    use RefreshDatabase;

    private const TE_PINTA = [
        'name' => 'Te Pinta',
        'eyebrow' => 'Te Pinta · Bell Ville',
        'headline' => '¿Qué te pinta hoy?',
        'headline_highlight' => 'pinta',
        'intro' => 'Empanadas caseras, pizzas y más. Pedilas para llevar, recibilas en casa o vení a comer al local.',
        'home_title' => 'Empanadas y pizzas',
        'highlights' => [
            'Comé en el local, con servicio a la mesa',
            'Retiro o envío a domicilio',
            'Confirmás tu pedido por WhatsApp',
        ],
        'footer_text' => 'Elegí del menú, armá tu pedido y confirmalo por WhatsApp.',
        'location' => 'Bell Ville, Córdoba',
        'coming_soon_text' => 'Muy pronto vas a poder pedir tus empanadas desde acá. Y se vienen novedades.',
        'empty_cart_text' => 'El menú te espera con nuestras empanadas. Sumá las que quieras y volvé por acá.',
    ];

    private const CAFE = [
        'name' => 'La Esquina',
        'eyebrow' => 'Café y pastas',
        'headline' => 'Un café como en casa.',
        'headline_highlight' => 'como en casa',
        'intro' => 'Desayunos, meriendas y pastas frescas.',
        'home_title' => 'Café, pastas y panificación',
        'highlights' => ['Pastas frescas todos los días', 'Retiro en el local'],
        'footer_text' => 'Pedí online y retirá en el local.',
        'location' => 'Villa María, Córdoba',
        'coming_soon_text' => 'Muy pronto vas a poder pedir desde acá.',
        'empty_cart_text' => 'Sumá productos desde el menú y volvé por acá.',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        // The general settings page requires the seeded raw price discounts.
        RawPriceDiscount::query()->insert([
            ['pricing_mode' => PriceTier::Unit->value, 'amount' => 200],
            ['pricing_mode' => PriceTier::HalfDozen->value, 'amount' => 1000],
            ['pricing_mode' => PriceTier::Dozen->value, 'amount' => 2000],
        ]);
    }

    public function test_te_pinta_keeps_its_current_texts_after_the_migration(): void
    {
        config(['public_site.open' => true]);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('public/home')
            ->where('restaurant', self::TE_PINTA));
    }

    public function test_the_coming_soon_screen_receives_the_restaurant_identity(): void
    {
        config(['public_site.open' => false]);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('public/coming-soon')
            ->where('restaurant.name', 'Te Pinta')
            ->where('restaurant.location', 'Bell Ville, Córdoba'));
    }

    public function test_admin_receives_the_current_identity_on_the_general_settings_page(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('admin.settings.general.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('admin/settings/general', false)
                ->where('identity', self::TE_PINTA));
    }

    public function test_admin_saves_another_installation_identity_and_the_public_site_shows_it(): void
    {
        config(['public_site.open' => true]);

        $this->actingAs(User::factory()->administrator()->create())
            ->from(route('admin.settings.general.index'))
            ->put(route('admin.settings.general.identity.update'), [
                ...self::CAFE,
                'name' => '  La Esquina  ',
                'highlights' => ['Pastas frescas todos los días', '', 'Retiro en el local'],
            ])
            ->assertRedirect(route('admin.settings.general.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Identidad del restaurante actualizada.');

        $this->assertSame('La Esquina', DB::table('restaurants')->where('id', 1)->value('name'));
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->where('restaurant', self::CAFE));
    }

    public function test_optional_texts_can_be_cleared(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->put(route('admin.settings.general.identity.update'), ['name' => 'La Esquina'])
            ->assertSessionHasNoErrors();

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->where('restaurant', [
                'name' => 'La Esquina',
                'eyebrow' => null,
                'headline' => null,
                'headline_highlight' => null,
                'intro' => null,
                'home_title' => null,
                'highlights' => [],
                'footer_text' => null,
                'location' => null,
                'coming_soon_text' => null,
                'empty_cart_text' => null,
            ]));
    }

    public function test_invalid_identity_is_rejected_without_changes(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->put(route('admin.settings.general.identity.update'), [...self::CAFE, 'name' => ''])
            ->assertSessionHasErrors(['name' => 'Escribí el nombre del restaurante.']);

        $this->actingAs($admin)
            ->put(route('admin.settings.general.identity.update'), [...self::CAFE, 'headline_highlight' => 'empanada'])
            ->assertSessionHasErrors(['headline_highlight' => 'La parte destacada tiene que aparecer tal cual en el título.']);

        $this->actingAs($admin)
            ->put(route('admin.settings.general.identity.update'), [...self::CAFE, 'headline' => null])
            ->assertSessionHasErrors(['headline_highlight' => 'La parte destacada tiene que aparecer tal cual en el título.']);

        $this->actingAs($admin)
            ->put(route('admin.settings.general.identity.update'), [...self::CAFE, 'highlights' => ['a', 'b', 'c', 'd']])
            ->assertSessionHasErrors(['highlights' => 'Cargá como máximo 3 frases destacadas.']);

        $this->actingAs($admin)
            ->put(route('admin.settings.general.identity.update'), [...self::CAFE, 'name' => str_repeat('a', 61)])
            ->assertSessionHasErrors('name');

        $this->assertSame('Te Pinta', DB::table('restaurants')->where('id', 1)->value('name'));
    }

    public function test_identity_update_only_changes_the_restaurant_in_context(): void
    {
        DB::table('restaurants')->insert([
            'id' => 2,
            'name' => 'Otra cocina',
            'slug' => 'otra-cocina',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->app->instance(RestaurantContext::class, new RestaurantContext(2));

        $this->actingAs(User::factory()->administrator()->create())
            ->put(route('admin.settings.general.identity.update'), self::CAFE)
            ->assertSessionHasNoErrors();

        $this->assertSame('Te Pinta', DB::table('restaurants')->where('id', 1)->value('name'));
        $this->assertSame('La Esquina', DB::table('restaurants')->where('id', 2)->value('name'));
    }

    public function test_guest_cannot_change_the_identity(): void
    {
        $this->put(route('admin.settings.general.identity.update'), self::CAFE)
            ->assertRedirect(route('login'));

        $this->assertSame('Te Pinta', DB::table('restaurants')->where('id', 1)->value('name'));
    }

    /** @return iterable<string, array{Role, bool, int}> */
    public static function identityMemberships(): iterable
    {
        yield 'owner' => [Role::Owner, true, 302];
        yield 'manager' => [Role::Manager, true, 302];
        yield 'cashier' => [Role::Cashier, true, 403];
        yield 'waiter' => [Role::Waiter, true, 403];
        yield 'kitchen' => [Role::Kitchen, true, 403];
        yield 'accountant' => [Role::Accountant, true, 403];
        yield 'inactive owner' => [Role::Owner, false, 403];
    }

    #[DataProvider('identityMemberships')]
    public function test_identity_uses_the_same_permission_as_general_settings(Role $role, bool $active, int $status): void
    {
        $this->actingAs(User::factory()->withRole($role, $active)->create())
            ->put(route('admin.settings.general.identity.update'), self::CAFE)
            ->assertStatus($status);
    }

    public function test_product_seo_uses_the_restaurant_name(): void
    {
        config(['public_site.open' => true]);
        DB::table('restaurants')->where('id', 1)->update(['name' => 'La Esquina']);
        $category = Category::query()->create(['name' => 'Panificación', 'slug' => 'panificacion', 'is_active' => true]);
        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Medialuna',
            'slug' => 'medialuna',
            'is_available' => true,
            'unit_price' => 1600,
            'half_dozen_price' => 9000,
            'dozen_price' => 16000,
        ]);

        $this->get(route('public.products.show', ['slug' => 'medialuna']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('seo.title', 'Medialuna | La Esquina')
                ->where('seo.description', 'Conocé Medialuna y hacé tu pedido en La Esquina.'));
    }
}
