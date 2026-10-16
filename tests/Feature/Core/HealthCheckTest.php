<?php

namespace Tests\Feature\Core;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\TestCase;

final class HealthCheckTest extends TestCase
{
    public function test_health_check_succeeds_when_the_database_answers(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_health_check_fails_when_the_database_is_unreachable(): void
    {
        $port = Config::get('database.connections.pgsql.port');
        Config::set('database.connections.pgsql.port', 1);
        DB::purge('pgsql');

        try {
            $this->get('/up')->assertStatus(500);
        } finally {
            Config::set('database.connections.pgsql.port', $port);
            DB::purge('pgsql');
        }
    }

    public function test_resolving_a_connection_does_not_connect_until_the_first_query(): void
    {
        $port = Config::get('database.connections.pgsql.port');
        Config::set('database.connections.pgsql.port', 1);
        DB::purge('pgsql');

        try {
            // The application boots (e.g. `artisan serve` after a host reboot)
            // before PostgreSQL is up; resolving the connection must not fail.
            $this->assertSame('pgsql', DB::connection('pgsql')->getName());
        } finally {
            Config::set('database.connections.pgsql.port', $port);
            DB::purge('pgsql');
        }
    }

    public function test_an_unreachable_database_fails_once_instead_of_reconnecting_forever(): void
    {
        $port = Config::get('database.connections.pgsql.port');
        Config::set('database.connections.pgsql.port', 1);
        DB::purge('pgsql');
        $memoryBefore = memory_get_usage();

        try {
            DB::select('select 1');
            $this->fail('The query should fail while the database is unreachable.');
        } catch (QueryException|PDOException) {
            $this->assertLessThan(10 * 1024 * 1024, memory_get_usage() - $memoryBefore);
        } finally {
            Config::set('database.connections.pgsql.port', $port);
            DB::purge('pgsql');
        }
    }
}
