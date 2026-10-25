<?php

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $weekday ISO weekday, 1 = Monday
 * @property ?string $opens_at HH:MM, null when the day is closed
 * @property ?string $closes_at HH:MM; at or before opens_at it ends the next day
 */
final class PublicOrderingHour extends Model
{
    protected $fillable = ['weekday', 'opens_at', 'closes_at'];

    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }

    /** @return Attribute<?string, ?string> */
    protected function opensAt(): Attribute
    {
        return Attribute::make(get: self::hoursAndMinutes(...), set: fn (?string $value): ?string => $value);
    }

    /** @return Attribute<?string, ?string> */
    protected function closesAt(): Attribute
    {
        return Attribute::make(get: self::hoursAndMinutes(...), set: fn (?string $value): ?string => $value);
    }

    /** Postgres returns HH:MM:SS; the app speaks HH:MM. */
    private static function hoursAndMinutes(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, 5);
    }
}
