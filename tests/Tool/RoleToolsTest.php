<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tests\Tool;

use Elabx\ProcessWireMcp\Tests\ProcessWireTestCase;
use Elabx\ProcessWireMcp\Tool\Core\RoleTools;

class RoleToolsTest extends ProcessWireTestCase
{
    private RoleTools $tools;

    /** @var string[] Role names created during tests, to be cleaned up in tearDown */
    private array $createdRoles = [];

    /** @var string[] Permission names created during tests, to be cleaned up in tearDown */
    private array $createdPermissions = [];

    /** @var string[] Template names created during tests, to be cleaned up in tearDown */
    private array $createdTemplates = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new RoleTools();
        $this->tools->setWire($this->wire());
    }

    protected function tearDown(): void
    {
        // Delete templates first
        foreach ($this->createdTemplates as $name) {
            $t = $this->wire()->wire('templates')->get($name);
            if ($t && $t->id) {
                $fg = $t->fieldgroup;
                $this->wire()->wire('templates')->delete($t);
                $this->wire()->wire('fieldgroups')->delete($fg);
            }
        }

        // Delete roles
        foreach ($this->createdRoles as $name) {
            $role = $this->wire()->wire('roles')->get($name);
            if ($role && $role->id) {
                $this->wire()->wire('roles')->delete($role);
            }
        }

        // Delete permissions
        foreach ($this->createdPermissions as $name) {
            $perm = $this->wire()->wire('permissions')->get($name);
            if ($perm && $perm->id) {
                $this->wire()->wire('permissions')->delete($perm);
            }
        }

        parent::tearDown();
    }

    public function testCreateRole(): void
    {
        $result = $this->tools->createRole('mcp_test_role');
        $this->createdRoles[] = 'mcp_test_role';

        $this->assertTrue($result['success']);
        $this->assertEquals('mcp_test_role', $result['data']['name']);
    }

    public function testCreateRoleWithPermissions(): void
    {
        $result = $this->tools->createRole('mcp_test_role_perms', ['page-view']);
        $this->createdRoles[] = 'mcp_test_role_perms';

        $this->assertTrue($result['success']);
        $this->assertContains('page-view', $result['data']['permissions']);
    }

    public function testCreateRoleAlreadyExists(): void
    {
        $this->tools->createRole('mcp_test_role_dup');
        $this->createdRoles[] = 'mcp_test_role_dup';

        $result = $this->tools->createRole('mcp_test_role_dup');

        $this->assertFalse($result['success']);
    }

    public function testUpdateRoleAddPermission(): void
    {
        $this->tools->createRole('mcp_test_role_upd');
        $this->createdRoles[] = 'mcp_test_role_upd';

        $result = $this->tools->updateRole('mcp_test_role_upd', addPermissions: ['page-view']);

        $this->assertTrue($result['success']);
        $this->assertContains('page-view', $result['data']['permissions']);
    }

    public function testUpdateRoleRemovePermission(): void
    {
        $this->tools->createRole('mcp_test_role_rm', ['page-view']);
        $this->createdRoles[] = 'mcp_test_role_rm';

        $result = $this->tools->updateRole('mcp_test_role_rm', removePermissions: ['page-view']);

        $this->assertTrue($result['success']);
        $this->assertNotContains('page-view', $result['data']['permissions']);
    }

    public function testUpdateRoleNotFound(): void
    {
        $result = $this->tools->updateRole('nonexistent_role_xyz');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testUpdateRoleGuestBlocked(): void
    {
        $result = $this->tools->updateRole('guest', addPermissions: ['page-view']);

        $this->assertFalse($result['success']);
    }

    public function testUpdateRoleSuperuserBlocked(): void
    {
        $result = $this->tools->updateRole('superuser', addPermissions: ['page-view']);

        $this->assertFalse($result['success']);
    }

    public function testDeleteRole(): void
    {
        $this->tools->createRole('mcp_test_role_del');
        $this->createdRoles[] = 'mcp_test_role_del';

        $result = $this->tools->deleteRole('mcp_test_role_del');

        $this->assertTrue($result['success']);

        // Remove from createdRoles since already deleted
        $this->createdRoles = array_diff($this->createdRoles, ['mcp_test_role_del']);
    }

    public function testDeleteRoleNotFound(): void
    {
        $result = $this->tools->deleteRole('nonexistent_role_xyz');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testDeleteRoleGuestBlocked(): void
    {
        $result = $this->tools->deleteRole('guest');

        $this->assertFalse($result['success']);
    }

    public function testDeleteRoleSuperuserBlocked(): void
    {
        $result = $this->tools->deleteRole('superuser');

        $this->assertFalse($result['success']);
    }

    public function testListPermissions(): void
    {
        $result = $this->tools->listPermissions();

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['data']['count']);
        $this->assertArrayHasKey('name', $result['data']['permissions'][0]);
    }

    public function testCreatePermission(): void
    {
        $result = $this->tools->createPermission('mcp-test-permission', 'Test Permission');
        $this->createdPermissions[] = 'mcp-test-permission';

        $this->assertTrue($result['success']);
        $this->assertEquals('mcp-test-permission', $result['data']['name']);
    }

    public function testCreatePermissionAlreadyExists(): void
    {
        $this->tools->createPermission('mcp-test-perm-dup', 'Dup Permission');
        $this->createdPermissions[] = 'mcp-test-perm-dup';

        $result = $this->tools->createPermission('mcp-test-perm-dup', 'Dup Permission');

        $this->assertFalse($result['success']);
    }

    public function testSetTemplateAccess(): void
    {
        // Create template
        $t = $this->wire()->wire('templates')->add('mcp_test_tpl_access');
        $t->save();
        $this->createdTemplates[] = 'mcp_test_tpl_access';

        // Create role
        $this->tools->createRole('mcp_test_role_access');
        $this->createdRoles[] = 'mcp_test_role_access';

        $result = $this->tools->setTemplateAccess('mcp_test_tpl_access', 'mcp_test_role_access', 'view', 'grant');

        $this->assertTrue($result['success']);
    }

    public function testSetTemplateAccessTemplateNotFound(): void
    {
        $result = $this->tools->setTemplateAccess('nonexistent_tpl_xyz', 'guest', 'view', 'grant');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testSetTemplateAccessRoleNotFound(): void
    {
        $result = $this->tools->setTemplateAccess('basic-page', 'nonexistent_role_xyz', 'view', 'grant');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }
}
