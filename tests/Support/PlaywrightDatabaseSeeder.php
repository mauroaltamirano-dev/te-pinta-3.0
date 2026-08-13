<?php

namespace Tests\Support;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class PlaywrightDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->administrator()->create([
            'name' => 'Playwright Administrator',
            'email' => 'playwright-admin@example.test',
            'password' => Hash::make('playwright-password'),
        ]);

        $this->call(ProductCatalogSeeder::class);

        Customer::query()->create([
            'name' => 'Cliente habitual',
            'phone' => '11 5555-0101',
            'phone_normalized' => '1155550101',
            'address' => 'Av. Siempre Viva 123',
        ]);

        DB::table('order_settings')->where('key', 'whatsapp_order_phone')->update(['string_value' => '5491100000000']);
    }
}
