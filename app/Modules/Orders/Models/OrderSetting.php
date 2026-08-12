<?php

namespace App\Modules\Orders\Models;

use App\Modules\Orders\Enums\OrderSettingKey;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class OrderSetting extends Model
{
    protected $fillable = ['key', 'integer_value', 'string_value'];

    protected function casts(): array
    {
        return ['key' => OrderSettingKey::class, 'integer_value' => 'integer'];
    }

    public static function integer(OrderSettingKey $key): int
    {
        $value = self::query()->where('key', $key->value)->value('integer_value');

        if ($value === null) {
            throw new LogicException("Missing integer order setting {$key->value}.");
        }

        return (int) $value;
    }

    public static function string(OrderSettingKey $key): string
    {
        $value = self::query()->where('key', $key->value)->value('string_value');

        if ($value === null) {
            throw new LogicException("Missing string order setting {$key->value}.");
        }

        return $value;
    }
}
