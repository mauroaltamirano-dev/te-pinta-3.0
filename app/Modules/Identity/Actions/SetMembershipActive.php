<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\MembershipGuard;
use Illuminate\Support\Facades\DB;

final readonly class SetMembershipActive
{
    public function __construct(private MembershipGuard $guard) {}

    public function execute(User $actor, User $member, bool $active): void
    {
        DB::transaction(function () use ($actor, $member, $active): void {
            $membership = $this->guard->lock($member);
            $this->guard->assertNotSelf($actor, $member, 'membership', 'No podés desactivar tu propio usuario.');

            if ($membership->is_active === $active) {
                return;
            }

            if (! $active) {
                $this->guard->assertAnotherActiveOwnerRemains($membership, 'membership');
            }

            $membership->forceFill(['is_active' => $active])->save();
        });
    }
}
