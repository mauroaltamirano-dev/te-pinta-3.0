<?php

namespace App\Core\Tenancy;

use Illuminate\Database\Connection;

/**
 * Holds the restaurant the current process acts for and exposes it to
 * PostgreSQL as the `app.current_restaurant_id` session setting, which is
 * the column default for every `restaurant_id`.
 */
final class RestaurantContext
{
    public const SESSION_SETTING = 'app.current_restaurant_id';

    public function __construct(private int $restaurantId) {}

    public function id(): int
    {
        return $this->restaurantId;
    }

    public function applyTo(Connection $connection): void
    {
        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        // Straight on PDO: Connection::statement() treats a refused connection as
        // "lost" and reconnects, which fires ConnectionEstablished again and
        // would recurse forever while the database is unreachable.
        $connection->getPdo()->exec(sprintf(
            "select set_config('%s', '%d', false)",
            self::SESSION_SETTING,
            $this->restaurantId,
        ));
    }
}
