<?php

namespace App\Modules\Identity\Support;

use App\Core\Tenancy\RestaurantContext;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\RestaurantMembership;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * Invariants shared by membership changes. Call inside a transaction.
 */
final readonly class MembershipGuard
{
    public function __construct(private RestaurantContext $restaurant) {}

    /**
     * Locks the member's row together with every active owner row of the
     * current restaurant, in one ordered statement so concurrent changes
     * serialize without deadlocking, and returns the member's row.
     *
     * @throws ModelNotFoundException when the user is not a member here
     */
    public function lock(User $member): RestaurantMembership
    {
        $rows = RestaurantMembership::query()
            ->where('restaurant_id', $this->restaurant->id())
            ->where(fn ($query) => $query
                ->where('user_id', $member->id)
                ->orWhere(fn ($owners) => $owners->where('role', Role::Owner->value)->where('is_active', true)))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        return $rows->firstWhere('user_id', $member->id)
            ?? throw (new ModelNotFoundException)->setModel(RestaurantMembership::class, [$member->id]);
    }

    public function assertNotSelf(User $actor, User $member, string $field, string $message): void
    {
        if ($actor->is($member)) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    /**
     * Rejects a change that would leave the restaurant without an active owner.
     */
    public function assertAnotherActiveOwnerRemains(RestaurantMembership $membership, string $field): void
    {
        if ($membership->role !== Role::Owner || ! $membership->is_active) {
            return;
        }

        $others = RestaurantMembership::query()
            ->where('restaurant_id', $membership->restaurant_id)
            ->where('role', Role::Owner->value)
            ->where('is_active', true)
            ->whereKeyNot($membership->id)
            ->count();

        if ($others === 0) {
            throw ValidationException::withMessages([$field => 'El restaurante necesita al menos un dueño activo.']);
        }
    }
}
