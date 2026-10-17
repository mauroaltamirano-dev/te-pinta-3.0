<?php

namespace Tests\Feature\Core;

use App\Core\Tenancy\RestaurantContext;
use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\Identity\Models\User;
use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Models\RawPriceDiscount;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class BrandingTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_root_view_injects_the_default_restaurant_accent(): void
    {
        $this->assertSame('#b84f3f', DB::table('restaurants')->where('id', 1)->value('accent_color'));

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('--brand-accent:#b84f3f;', false)
            ->assertSee('--brand-accent-text:#9c4336;', false)
            ->assertSee('--brand-on-accent:#ffffff;', false)
            ->assertSee('<meta name="theme-color" content="#b84f3f">', false);
    }

    public function test_root_view_follows_the_current_restaurant_accent_without_a_rebuild(): void
    {
        DB::table('restaurants')->where('id', 1)->update(['accent_color' => '#1f6f4a']);
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->get(route('admin.settings.general.index'))
            ->assertOk()
            ->assertSee('--brand-accent:#1f6f4a;', false)
            ->assertSee('--brand-accent-text:#1a5e3f;', false)
            ->assertDontSee('#b84f3f', false);
    }

    public function test_root_view_reads_the_accent_of_the_restaurant_in_context(): void
    {
        DB::table('restaurants')->insert([
            'id' => 2,
            'name' => 'Otra cocina',
            'slug' => 'otra-cocina',
            'accent_color' => '#1f6f4a',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->app->instance(RestaurantContext::class, new RestaurantContext(2));

        $this->get(route('login'))->assertOk()->assertSee('--brand-accent:#1f6f4a;', false);
    }

    public function test_root_view_no_longer_forces_or_detects_dark_mode(): void
    {
        $this->withUnencryptedCookie('appearance', 'dark')
            ->get(route('login'))
            ->assertOk()
            ->assertDontSee('<html lang="en" class="dark"', false)
            ->assertDontSee('prefers-color-scheme', false)
            ->assertDontSee('html.dark', false);
    }

    public function test_database_rejects_accents_that_are_not_lowercase_hex(): void
    {
        $this->expectException(QueryException::class);

        DB::table('restaurants')->where('id', 1)->update(['accent_color' => 'red']);
    }

    public function test_invalid_direct_sql_color_is_never_rendered_as_an_unreadable_theme(): void
    {
        DB::table('restaurants')->where('id', 1)->update(['accent_color' => '#ffffff']);

        $this->get(route('login'))->assertOk()->assertSee('--brand-accent:#b84f3f;', false);
    }

    public function test_inertia_navigation_carries_the_current_branding_variables(): void
    {
        DB::table('restaurants')->where('id', 1)->update(['accent_color' => '#1f6f4a']);

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('admin.settings.general.index'), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(Request::create('/')),
            ])
            ->assertOk()
            ->assertJsonPath('props.brand.accent', '#1f6f4a')
            ->assertJsonPath('props.brand.variables.--brand-accent', '#1f6f4a')
            ->assertJsonPath('props.brand.variables.--brand-on-accent', '#ffffff');
    }
}
