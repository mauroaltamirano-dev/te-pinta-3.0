<?php

namespace App\Modules\Identity\Models;

use App\Core\Tenancy\RestaurantContext;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Enums\StaffProfile;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property StaffProfile|null $staff_profile legacy, kept one release for rollback; never read
 * @property bool $is_admin legacy, kept one release for rollback; never read
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read RestaurantMembership|null $currentMembership
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'is_admin', 'staff_profile', 'currentMembership', 'memberships'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * @return HasMany<RestaurantMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(RestaurantMembership::class);
    }

    /**
     * The membership in the restaurant this process acts for, active or not.
     *
     * @return HasOne<RestaurantMembership, $this>
     */
    public function currentMembership(): HasOne
    {
        return $this->hasOne(RestaurantMembership::class)
            ->where('restaurant_id', app(RestaurantContext::class)->id());
    }

    /**
     * The role of the active membership in the current restaurant, if any.
     */
    public function role(): ?Role
    {
        $membership = $this->currentMembership;

        return $membership !== null && $membership->is_active ? $membership->role : null;
    }

    public function hasPermission(Permission $permission): bool
    {
        return $this->role()?->grants($permission) ?? false;
    }

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        return array_map(fn (Permission $permission) => $permission->value, $this->role()?->permissions() ?? []);
    }

    public function canUsePos(): bool
    {
        return $this->hasPermission(Permission::OrdersManage);
    }

    public function canOperateCashier(): bool
    {
        return $this->role()?->operatesCashier() ?? false;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'staff_profile' => StaffProfile::class,
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
