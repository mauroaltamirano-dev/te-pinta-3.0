<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Core\Tenancy\RestaurantContext;
use App\Modules\Identity\Actions\ChangeMemberRole;
use App\Modules\Identity\Actions\CreateMember;
use App\Modules\Identity\Actions\SetMembershipActive;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Http\Requests\StoreMemberRequest;
use App\Modules\Identity\Http\Requests\UpdateMemberRoleRequest;
use App\Modules\Identity\Models\RestaurantMembership;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class UserManagementController
{
    public function index(Request $request, RestaurantContext $restaurant): Response
    {
        $members = RestaurantMembership::query()
            ->with('user:id,name,email')
            ->where('restaurant_id', $restaurant->id())
            ->get()
            ->sortBy(fn (RestaurantMembership $membership) => mb_strtolower($membership->user->name))
            ->values()
            ->map(fn (RestaurantMembership $membership) => [
                'id' => $membership->user_id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'role' => $membership->role->value,
                'role_label' => $membership->role->label(),
                'is_active' => $membership->is_active,
            ]);

        return Inertia::render('admin/users/index', [
            'members' => $members,
            'roles' => array_map(fn (Role $role) => ['value' => $role->value, 'label' => $role->label()], Role::cases()),
            'currentUserId' => $request->user()?->getAuthIdentifier(),
        ]);
    }

    public function store(StoreMemberRequest $request, CreateMember $action): RedirectResponse
    {
        $action->execute(
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            Role::from($request->string('role')->toString()),
        );

        return to_route('admin.users.index')->with('success', 'Usuario creado.');
    }

    public function updateRole(UpdateMemberRoleRequest $request, User $user, ChangeMemberRole $action): RedirectResponse
    {
        $action->execute($this->actor($request), $user, $request->role());

        return to_route('admin.users.index')->with('success', 'Rol actualizado.');
    }

    public function deactivate(Request $request, User $user, SetMembershipActive $action): RedirectResponse
    {
        $action->execute($this->actor($request), $user, false);

        return to_route('admin.users.index')->with('success', 'Usuario desactivado.');
    }

    public function activate(Request $request, User $user, SetMembershipActive $action): RedirectResponse
    {
        $action->execute($this->actor($request), $user, true);

        return to_route('admin.users.index')->with('success', 'Usuario reactivado.');
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
