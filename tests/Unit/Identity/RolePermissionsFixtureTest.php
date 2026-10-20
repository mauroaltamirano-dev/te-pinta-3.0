<?php

namespace Tests\Unit\Identity;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;
use PHPUnit\Framework\TestCase;

/**
 * The admin navigation tests (tests/frontend) read role permissions from a
 * JSON fixture; this keeps that fixture equal to the backend role mapping.
 */
final class RolePermissionsFixtureTest extends TestCase
{
    public function test_the_frontend_fixture_matches_every_role_permission(): void
    {
        $fixture = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2).'/frontend/fixtures/role-permissions.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $expected = [];

        foreach (Role::cases() as $role) {
            $expected[$role->value] = array_map(fn (Permission $permission) => $permission->value, $role->permissions());
        }

        $this->assertSame($expected, $fixture);
    }
}
