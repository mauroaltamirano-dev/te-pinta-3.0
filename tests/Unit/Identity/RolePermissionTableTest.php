<?php

namespace Tests\Unit\Identity;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;
use PHPUnit\Framework\TestCase;

final class RolePermissionTableTest extends TestCase
{
    /** The S2 table of odd/tasks/rbac.md, row by row. */
    private const TABLE = [
        'dashboard.view' => ['owner', 'manager', 'cashier', 'accountant'],
        'orders.view' => ['owner', 'manager', 'cashier', 'waiter', 'kitchen', 'accountant'],
        'orders.manage' => ['owner', 'manager', 'cashier', 'waiter'],
        'customers.view' => ['owner', 'manager', 'cashier', 'waiter', 'accountant'],
        'customers.manage' => ['owner', 'manager', 'cashier'],
        'catalog.view' => ['owner', 'manager', 'cashier', 'waiter', 'kitchen', 'accountant'],
        'catalog.manage' => ['owner', 'manager'],
        'inventory.view' => ['owner', 'manager', 'kitchen', 'accountant'],
        'inventory.manage' => ['owner', 'manager'],
        'finance.view' => ['owner', 'manager', 'accountant'],
        'finance.manage' => ['owner', 'manager'],
        'marketing.manage' => ['owner', 'manager'],
        'floor.manage' => ['owner', 'manager'],
        'settings.manage' => ['owner'],
        'users.manage' => ['owner'],
    ];

    public function test_roles_are_the_six_code_defined_roles(): void
    {
        $this->assertSame(
            ['owner', 'manager', 'cashier', 'waiter', 'kitchen', 'accountant'],
            array_map(fn (Role $role) => $role->value, Role::cases()),
        );
        $this->assertSame(
            ['Dueño', 'Encargado', 'Cajero', 'Mozo', 'Cocina', 'Contador'],
            array_map(fn (Role $role) => $role->label(), Role::cases()),
        );
    }

    public function test_role_permissions_match_the_specified_table(): void
    {
        $this->assertSame(array_keys(self::TABLE), array_map(fn (Permission $permission) => $permission->value, Permission::cases()));

        foreach (self::TABLE as $permission => $roles) {
            foreach (Role::cases() as $role) {
                $this->assertSame(
                    in_array($role->value, $roles, true),
                    $role->grants(Permission::from($permission)),
                    "{$role->value} × {$permission}",
                );
            }
        }
    }
}
