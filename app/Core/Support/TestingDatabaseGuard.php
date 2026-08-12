<?php

namespace App\Core\Support;

use RuntimeException;

final class TestingDatabaseGuard
{
    public static function ensureSafe(string $environment, ?string $database, ?string $url = null): void
    {
        if ($environment !== 'testing') {
            return;
        }

        $effectiveDatabase = self::databaseFromUrl($url) ?? $database;

        if (! self::isExplicitlyTestDatabase($effectiveDatabase)) {
            throw new RuntimeException(sprintf(
                'Refusing to run APP_ENV=testing against database [%s]. Use a database name containing a standalone "test" or "testing" segment.',
                $effectiveDatabase ?: 'not configured',
            ));
        }
    }

    private static function databaseFromUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) ? rawurldecode(ltrim($path, '/')) : '';
    }

    private static function isExplicitlyTestDatabase(?string $database): bool
    {
        if ($database === ':memory:') {
            return true;
        }

        if ($database === null || trim($database) === '') {
            return false;
        }

        return preg_match('/(^|[._-])(test|testing)([._-]|$)/i', $database) === 1;
    }
}
