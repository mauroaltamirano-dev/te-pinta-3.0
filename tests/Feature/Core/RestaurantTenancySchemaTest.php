<?php

namespace Tests\Feature\Core;

use App\Core\Tenancy\RestaurantContext;
use App\Modules\Products\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class RestaurantTenancySchemaTest extends TestCase
{
    use RefreshDatabase;

    private const GLOBAL_TABLES = [
        'cache',
        'cache_locks',
        'failed_jobs',
        'inventory_units',
        'job_batches',
        'jobs',
        'migrations',
        'passkeys',
        'password_reset_tokens',
        'restaurants',
        'sessions',
        'users',
    ];

    public function test_the_default_restaurant_exists(): void
    {
        $this->assertDatabaseHas('restaurants', ['id' => 1, 'slug' => 'te-pinta']);
        $this->assertSame(1, app(RestaurantContext::class)->id());
    }

    public function test_every_business_table_has_a_required_restaurant_foreign_key(): void
    {
        $tables = DB::table('pg_tables')
            ->where('schemaname', 'public')
            ->whereNotIn('tablename', self::GLOBAL_TABLES)
            ->orderBy('tablename')
            ->pluck('tablename');

        $this->assertNotEmpty($tables);

        foreach ($tables as $table) {
            $column = DB::table('information_schema.columns')
                ->where('table_schema', 'public')
                ->where('table_name', $table)
                ->where('column_name', 'restaurant_id')
                ->first();

            $this->assertNotNull($column, "{$table} is missing restaurant_id");
            $this->assertSame('NO', $column->is_nullable, "{$table}.restaurant_id must be NOT NULL");

            $foreignKeys = DB::selectOne(
                "select count(*) as total
                 from pg_constraint c
                 join pg_attribute a on a.attrelid = c.conrelid and a.attnum = any(c.conkey)
                 where c.contype = 'f'
                   and c.conrelid = ?::regclass
                   and c.confrelid = 'restaurants'::regclass
                   and a.attname = 'restaurant_id'",
                [$table],
            );

            $this->assertSame(1, (int) $foreignKeys->total, "{$table}.restaurant_id must reference restaurants");
        }
    }

    public function test_new_rows_belong_to_the_current_restaurant_without_explicit_assignment(): void
    {
        $category = Category::query()->create(['name' => 'Empanadas', 'slug' => 'empanadas']);

        $this->assertSame(1, (int) DB::table('categories')->where('id', $category->id)->value('restaurant_id'));
    }

    public function test_rows_cannot_be_written_without_a_restaurant_context(): void
    {
        DB::statement("select set_config('app.current_restaurant_id', '', false)");

        $this->expectException(QueryException::class);

        DB::table('categories')->insert(['name' => 'Sin contexto', 'slug' => 'sin-contexto']);
    }

    public function test_unique_business_keys_are_scoped_per_restaurant(): void
    {
        DB::table('restaurants')->insert(['id' => 2, 'name' => 'Otro', 'slug' => 'otro']);

        DB::table('categories')->insert(['name' => 'Pizzas', 'slug' => 'pizzas']);
        $this->useRestaurant(2);
        DB::table('categories')->insert(['name' => 'Pizzas', 'slug' => 'pizzas']);

        $this->assertSame(2, DB::table('categories')->where('slug', 'pizzas')->count());

        $this->expectException(QueryException::class);
        DB::table('categories')->insert(['name' => 'Pizzas 2', 'slug' => 'pizzas']);
    }

    public function test_singleton_rules_are_scoped_per_restaurant(): void
    {
        DB::table('restaurants')->insert(['id' => 2, 'name' => 'Otro', 'slug' => 'otro']);

        DB::table('wallets')->insert(['name' => 'Caja A', 'is_order_payment_default' => true]);
        $this->useRestaurant(2);
        DB::table('wallets')->insert(['name' => 'Caja B', 'is_order_payment_default' => true]);

        $this->assertSame(2, DB::table('wallets')->where('is_order_payment_default', true)->count());

        $this->expectException(QueryException::class);
        DB::table('wallets')->insert(['name' => 'Caja C', 'is_order_payment_default' => true]);
    }

    private function useRestaurant(int $restaurantId): void
    {
        DB::statement("select set_config('app.current_restaurant_id', ?, false)", [(string) $restaurantId]);
    }
}
