<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\MembershipGuard;
use Illuminate\Support\Facades\DB;

final readonly class ChangeMemberRole
{
    public function __construct(private MembershipGuard $guard) {}

    public function execute(User $actor, User $member, Role $role): void
    {
        DB::transaction(function () use ($actor, $member, $role): void {
            $membership = $this->guard->lock($member);
            $this->guard->assertNotSelf($actor, $member, 'role', 'No podés cambiar tu propio rol.');

            if ($membership->role === $role) {
                return;
            }

            if ($role !== Role::Owner) {
                $this->guard->assertAnotherActiveOwnerRemains($membership, 'role');
            }

            $membership->forceFill(['role' => $role])->save();
        });
    }
}
