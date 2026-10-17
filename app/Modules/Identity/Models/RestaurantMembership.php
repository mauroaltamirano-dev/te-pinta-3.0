<?php

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Enums\StaffProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user's staff access to one restaurant. `restaurant_id` defaults to the
 * current restaurant session setting, like every business row.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $user_id
 * @property Role $role
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class RestaurantMembership extends Model
{
    protected $table = 'restaurant_user';

    protected $guarded = ['id', 'restaurant_id', 'user_id', 'role', 'is_active'];

    protected static function booted(): void
    {
        self::saved(fn (self $membership) => $membership->mirrorLegacyAccess());
    }

    /**
     * Keeps `users.is_admin` / `users.staff_profile` in step with this
     * membership for one release, so rolling back to a release that still
     * reads them grants the same access. Roles the legacy columns cannot
     * express (manager, kitchen, accountant) and inactive members get none.
     */
    private function mirrorLegacyAccess(): void
    {
        $role = $this->is_active ? $this->role : null;

        User::query()->whereKey($this->user_id)->toBase()->update([
            'is_admin' => $role === Role::Owner,
            'staff_profile' => match ($role) {
                Role::Waiter => StaffProfile::Waiter->value,
                Role::Cashier => StaffProfile::Cashier->value,
                default => null,
            },
        ]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }
}
