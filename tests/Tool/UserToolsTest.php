<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tests\Tool;

use Elabx\ProcessWireMcp\Tests\ProcessWireTestCase;
use Elabx\ProcessWireMcp\Tool\Core\UserTools;

class UserToolsTest extends ProcessWireTestCase
{
    private UserTools $tools;

    /** @var string[] User names created during tests, to be cleaned up in tearDown */
    private array $createdUsers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new UserTools();
        $this->tools->setWire($this->wire());
    }

    protected function tearDown(): void
    {
        foreach ($this->createdUsers as $name) {
            $user = $this->wire()->wire('users')->get($name);
            if ($user && $user->id) {
                $this->wire()->wire('users')->delete($user);
            }
        }

        parent::tearDown();
    }

    public function testListUsers(): void
    {
        $result = $this->tools->listUsers();

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('count', $result['data']);
        $this->assertArrayHasKey('users', $result['data']);
        $this->assertGreaterThanOrEqual(2, $result['data']['count']);
    }

    public function testListUsersFilterByRole(): void
    {
        $result = $this->tools->listUsers('superuser');

        $this->assertTrue($result['success']);
        foreach ($result['data']['users'] as $user) {
            $this->assertContains('superuser', $user['roles']);
        }
    }

    public function testGetUserByName(): void
    {
        $result = $this->tools->getUser('admin');

        $this->assertTrue($result['success']);
        $this->assertEquals('admin', $result['data']['name']);
        $this->assertTrue($result['data']['is_superuser']);
    }

    public function testGetUserNotFound(): void
    {
        $result = $this->tools->getUser('nonexistent_user_xyz');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testGetUserPasswordNeverExposed(): void
    {
        $result = $this->tools->getUser('admin');

        $this->assertTrue($result['success']);
        $this->assertArrayNotHasKey('pass', $result['data']);
    }

    public function testCreateUser(): void
    {
        $result = $this->tools->createUser('mcp_test_user', 'test@example.com', 'TestPass123!');
        $this->createdUsers[] = 'mcp_test_user';

        $this->assertTrue($result['success']);
        $this->assertEquals('mcp_test_user', $result['data']['user']['name']);
    }

    public function testCreateUserAlreadyExists(): void
    {
        $this->tools->createUser('mcp_test_dup', 'dup@example.com', 'TestPass123!');
        $this->createdUsers[] = 'mcp_test_dup';

        $result = $this->tools->createUser('mcp_test_dup', 'dup2@example.com', 'TestPass123!');

        $this->assertFalse($result['success']);
    }

    public function testUpdateUser(): void
    {
        $this->tools->createUser('mcp_test_upd', 'upd@example.com', 'TestPass123!');
        $this->createdUsers[] = 'mcp_test_upd';

        $result = $this->tools->updateUser('mcp_test_upd', email: 'updated@example.com');

        $this->assertTrue($result['success']);
        $this->assertEquals('updated@example.com', $result['data']['user']['email']);
    }

    public function testUpdateUserNotFound(): void
    {
        $result = $this->tools->updateUser('nonexistent_user_xyz', email: 'x@x.com');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testDeleteUser(): void
    {
        $this->tools->createUser('mcp_test_del', 'del@example.com', 'TestPass123!');
        $this->createdUsers[] = 'mcp_test_del';

        $result = $this->tools->deleteUser('mcp_test_del');

        $this->assertTrue($result['success']);

        // Remove from createdUsers since already deleted
        $this->createdUsers = array_diff($this->createdUsers, ['mcp_test_del']);
    }

    public function testDeleteSuperuserBlocked(): void
    {
        $result = $this->tools->deleteUser('admin');

        $this->assertFalse($result['success']);
    }

    public function testDeleteGuestBlocked(): void
    {
        $result = $this->tools->deleteUser('guest');

        $this->assertFalse($result['success']);
    }

    public function testListRoles(): void
    {
        $result = $this->tools->listRoles();

        $this->assertTrue($result['success']);
        $this->assertGreaterThanOrEqual(2, $result['data']['count']);

        $roleNames = array_column($result['data']['roles'], 'name');
        $this->assertContains('guest', $roleNames);
        $this->assertContains('superuser', $roleNames);
    }
}
