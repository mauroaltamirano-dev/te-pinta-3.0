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

        $connection->statement('select set_config(?, ?, false)', [
            self::SESSION_SETTING,
            (string) $this->restaurantId,
        ]);
    }
}
