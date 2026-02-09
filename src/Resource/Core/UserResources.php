<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Resource\Core;

use Elabx\ProcessWireMcp\Resource\ProcessWireMcpResource;
use Mcp\Capability\Attribute\McpResource;

/**
 * User resources for ProcessWire MCP
 *
 * Provides read-only access to user data.
 * Note: Passwords are NEVER exposed.
 */
class UserResources extends ProcessWireMcpResource
{
    public static function getResourceInfo(): array
    {
        return [
            'name' => 'user_resources',
            'description' => 'User data resources for ProcessWire (no passwords)',
            'priority' => 30,
        ];
    }

    /**
     * Get all users with basic info
     *
     * @return string JSON-encoded user data
     */
    #[McpResource(
        uri: 'users://list',
        name: 'user_list',
        description: 'All users with basic info (passwords never exposed)'
    )]
    public function getUserList(): string
    {
        $users = [];

        foreach ($this->users() as $user) {
            // Get role names
            $roles = [];
            foreach ($user->roles as $role) {
                if ($role->name !== 'guest') {
                    $roles[] = $role->name;
                }
            }

            $users[] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $roles,
                'is_superuser' => $user->isSuperuser(),
            ];
        }

        // Sort by name
        usort($users, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return json_encode([
            'count' => count($users),
            'users' => $users,
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Get all roles with permissions
     *
     * @return string JSON-encoded role data
     */
    #[McpResource(
        uri: 'roles://list',
        name: 'role_list',
        description: 'All user roles with their permissions'
    )]
    public function getRoleList(): string
    {
        $roles = [];
        $rolesApi = $this->wire->wire('roles');

        foreach ($rolesApi as $role) {
            // Get permissions
            $permissions = [];
            foreach ($role->permissions as $permission) {
                $permissions[] = $permission->name;
            }

            // Count users with this role
            $userCount = $this->users()->count("roles={$role->id}");

            $roles[] = [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $permissions,
                'user_count' => $userCount,
            ];
        }

        return json_encode([
            'count' => count($roles),
            'roles' => $roles,
        ], JSON_PRETTY_PRINT);
    }
}
