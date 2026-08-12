<?php

namespace Tests\Support;

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

        DB::table('order_settings')->where('key', 'whatsapp_order_phone')->update(['string_value' => '5491100000000']);
    }
}
