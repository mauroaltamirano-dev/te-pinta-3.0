<?php

namespace Tests\Feature\Core;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
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
}
