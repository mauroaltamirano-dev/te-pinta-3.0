<?php

namespace Tests\Feature\Settings;

use App\Core\Tenancy\RestaurantContext;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Models\RawPriceDiscount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class BrandingSettingsTest extends TestCase
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

    public function test_guest_and_non_admin_cannot_change_the_accent(): void
    {
        $this->put(route('admin.settings.general.branding.update'), ['accent_color' => '#1f6f4a'])
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->put(route('admin.settings.general.branding.update'), ['accent_color' => '#1f6f4a'])
            ->assertForbidden();

        $this->assertSame('#b84f3f', $this->storedAccent());
    }

    public function test_admin_receives_the_current_accent_on_the_general_settings_page(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('admin.settings.general.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('admin/settings/general', false)
                ->where('branding.accent_color', '#b84f3f'));
    }

    public function test_admin_saves_a_readable_accent_normalized_to_lowercase(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->from(route('admin.settings.general.index'))
            ->put(route('admin.settings.general.branding.update'), ['accent_color' => ' #1F6F4A '])
            ->assertRedirect(route('admin.settings.general.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Color de acento actualizado.');

        $this->assertSame('#1f6f4a', $this->storedAccent());
    }

    public function test_accent_without_enough_contrast_on_white_is_rejected(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->from(route('admin.settings.general.index'))
            ->put(route('admin.settings.general.branding.update'), ['accent_color' => '#ea7c69'])
            ->assertRedirect(route('admin.settings.general.index'))
            ->assertSessionHasErrors([
                'accent_color' => 'El color de acento no se distingue lo suficiente sobre fondo blanco (mínimo 3:1). Elegí un tono más oscuro.',
            ]);

        $this->assertSame('#b84f3f', $this->storedAccent());
    }

    public function test_accent_that_cannot_hold_a_readable_label_is_rejected(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->from(route('admin.settings.general.index'))
            ->put(route('admin.settings.general.branding.update'), ['accent_color' => '#c2581c'])
            ->assertSessionHasErrors([
                'accent_color' => 'El texto sobre el color de acento no alcanza contraste 4.5:1. Elegí un tono más oscuro.',
            ]);

        $this->assertSame('#b84f3f', $this->storedAccent());
    }

    public function test_malformed_accent_is_rejected(): void
    {
        foreach (['', 'red', '#b43', '#b84f3f;}body{', ['#b84f3f']] as $value) {
            $this->actingAs(User::factory()->administrator()->create())
                ->from(route('admin.settings.general.index'))
                ->put(route('admin.settings.general.branding.update'), ['accent_color' => $value])
                ->assertSessionHasErrors('accent_color');
        }

        $this->assertSame('#b84f3f', $this->storedAccent());
    }

    public function test_branding_update_only_changes_the_restaurant_in_context(): void
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
            ->put(route('admin.settings.general.branding.update'), ['accent_color' => '#1f6f4a'])
            ->assertRedirect(route('admin.settings.general.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('#b84f3f', $this->storedAccent());
        $this->assertSame('#1f6f4a', DB::table('restaurants')->where('id', 2)->value('accent_color'));
    }

    /** @return iterable<string, array{Role, bool, int}> */
    public static function brandingMemberships(): iterable
    {
        yield 'owner' => [Role::Owner, true, 302];
        yield 'manager' => [Role::Manager, true, 302];
        yield 'cashier' => [Role::Cashier, true, 403];
        yield 'waiter' => [Role::Waiter, true, 403];
        yield 'kitchen' => [Role::Kitchen, true, 403];
        yield 'accountant' => [Role::Accountant, true, 403];
        yield 'inactive owner' => [Role::Owner, false, 403];
    }

    #[DataProvider('brandingMemberships')]
    public function test_branding_uses_the_same_membership_permission_as_general_settings(Role $role, bool $active, int $status): void
    {
        $user = User::factory()->withRole($role, $active)->create();
        $this->actingAs($user)->get(route('admin.settings.general.index'))
            ->assertStatus($status === 302 ? 200 : 403);
        $this->actingAs($user)->put(route('admin.settings.general.branding.update'), ['accent_color' => '#1f6f4a'])
            ->assertStatus($status);
        $this->assertSame($status === 302 ? '#1f6f4a' : '#b84f3f', $this->storedAccent());
    }

    private function storedAccent(): ?string
    {
        return DB::table('restaurants')->where('id', 1)->value('accent_color');
    }
}
