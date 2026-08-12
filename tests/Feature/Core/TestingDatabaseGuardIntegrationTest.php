<?php

namespace Tests\Feature\Core;

use App\Providers\AppServiceProvider;
use RuntimeException;
use Tests\TestCase;

final class TestingDatabaseGuardIntegrationTest extends TestCase
{
    public function test_application_provider_rejects_a_non_test_database_in_testing(): void
    {
        config()->set('database.connections.pgsql.url', null);
        config()->set('database.connections.pgsql.database', 'tepinta');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing to run APP_ENV=testing');

        (new AppServiceProvider($this->app))->boot();
    }
}
