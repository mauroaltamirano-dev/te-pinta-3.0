<?php

namespace Tests\Unit\Core;

use App\Core\Support\TestingDatabaseGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TestingDatabaseGuardTest extends TestCase
{
    public function test_non_testing_environments_are_not_restricted(): void
    {
        TestingDatabaseGuard::ensureSafe('local', 'tepinta');

        $this->addToAssertionCount(1);
    }

    #[DataProvider('safeDatabaseNames')]
    public function test_testing_environment_accepts_only_explicit_test_database_names(string $database): void
    {
        TestingDatabaseGuard::ensureSafe('testing', $database);

        $this->addToAssertionCount(1);
    }

    /** @return array<string, array{string}> */
    public static function safeDatabaseNames(): array
    {
        return [
            'standard test database' => ['testing'],
            'playwright test database' => ['testing_playwright'],
            'project suffixed test database' => ['tepinta_test'],
            'hyphenated test database' => ['tepinta-testing'],
            'sqlite test path' => ['/tmp/tepinta.test.sqlite'],
            'in-memory sqlite database' => [':memory:'],
        ];
    }

    #[DataProvider('unsafeDatabaseNames')]
    public function test_testing_environment_rejects_database_names_without_a_test_segment(?string $database): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing to run APP_ENV=testing');

        TestingDatabaseGuard::ensureSafe('testing', $database);
    }

    /** @return array<string, array{?string}> */
    public static function unsafeDatabaseNames(): array
    {
        return [
            'development database' => ['tepinta'],
            'production database' => ['tepinta_production'],
            'test substring without boundaries' => ['contest'],
            'empty database' => [''],
            'missing database' => [null],
        ];
    }

    public function test_database_url_name_takes_precedence_over_database_setting(): void
    {
        $this->expectException(RuntimeException::class);

        TestingDatabaseGuard::ensureSafe(
            'testing',
            'testing',
            'postgresql://tepinta:secret@pgsql:5432/tepinta',
        );
    }

    public function test_explicit_test_database_in_url_is_accepted(): void
    {
        TestingDatabaseGuard::ensureSafe(
            'testing',
            'tepinta',
            'postgresql://tepinta:secret@pgsql:5432/testing_playwright',
        );

        $this->addToAssertionCount(1);
    }
}
