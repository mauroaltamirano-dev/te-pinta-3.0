<?php

namespace App\Core\Tenancy;

use Closure;
use Illuminate\Database\Connection;
use PDO;

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

    /**
     * Applies the setting when the connection actually connects. Connecting
     * eagerly here would make the application fail to boot (for example
     * `artisan serve` after a host reboot) while PostgreSQL is still down.
     */
    public function applyTo(Connection $connection): void
    {
        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        $connection->setPdo($this->withSetting($connection->getRawPdo()));
        $connection->setReadPdo($this->withSetting($connection->getRawReadPdo()));
    }

    private function withSetting(PDO|Closure|null $pdo): PDO|Closure|null
    {
        if ($pdo instanceof PDO) {
            $this->configure($pdo);

            return $pdo;
        }

        if ($pdo === null) {
            return null;
        }

        return function () use ($pdo): PDO {
            return $this->configure($pdo());
        };
    }

    private function configure(PDO $pdo): PDO
    {
        // Straight on PDO: Connection::statement() treats a refused connection
        // as lost and reconnects, which would fire ConnectionEstablished again.
        $pdo->exec(sprintf("select set_config('%s', '%d', false)", self::SESSION_SETTING, $this->restaurantId));

        return $pdo;
    }
}
