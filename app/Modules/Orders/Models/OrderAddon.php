<?php

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class OrderAddon extends Model
{
    use SoftDeletes;

    protected $fillable = ['slug', 'name', 'price', 'is_available', 'sort_order'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'is_available' => 'boolean', 'sort_order' => 'integer'];
    }

    /**
     * Resolve only currently sellable addons by their public stable slugs.
     *
     * @param  array<int, string>  $slugs
     * @return Collection<string, self>
     */
    public static function availableBySlugs(array $slugs): Collection
    {
        return self::query()
            ->whereIn('slug', array_values(array_unique($slugs)))
            ->where('is_available', true)
            ->get()
            ->keyBy('slug');
    }
}
