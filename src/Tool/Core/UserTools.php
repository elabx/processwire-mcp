<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tool\Core;

use Elabx\ProcessWireMcp\Tool\ProcessWireMcpTool;
use Mcp\Capability\Attribute\McpTool;
use ProcessWire\User;
use ProcessWire\NullPage;

/**
 * User management tools for ProcessWire MCP
 *
 * Provides tools for listing and inspecting users.
 * Note: Passwords are NEVER exposed for security.
 */
class UserTools extends ProcessWireMcpTool
{
    public static function getToolInfo(): array
    {
        return [
            'name' => 'user_tools',
            'description' => 'User inspection tools for ProcessWire (read-only, no passwords)',
            'priority' => 40,
        ];
    }

    /**
     * List users
     *
     * @param string|null $role Filter by role name
     * @param int $limit Maximum number of users to return
     * @param int $start Starting offset
     * @return array User list
     */
    #[McpTool(
        name: 'list_users',
        description: 'List users in the system. Optionally filter by role. Passwords are never exposed.'
    )]
    public function listUsers(?string $role = null, int $limit = 50, int $start = 0): array
    {
        try {
            $selector = "limit={$limit}, start={$start}";

            if ($role !== null) {
                $roleObj = $this->wire->wire('roles')->get($role);
                if (!$roleObj || !$roleObj->id) {
                    return $this->error("Role not found: {$role}", 'NOT_FOUND');
                }
                $selector .= ", roles={$roleObj->id}";
            }

            $users = $this->users()->find($selector);

            $results = [];
            foreach ($users as $user) {
                $results[] = $this->userToArray($user);
            }

            return $this->success([
                'count' => $users->count(),
                'total' => $users->getTotal(),
                'start' => $start,
                'limit' => $limit,
                'users' => $results,
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to list users: ' . $e->getMessage());
        }
    }

    /**
     * Get a single user
     *
     * @param int|string $identifier User ID or name
     * @return array User data
     */
    #[McpTool(
        name: 'get_user',
        description: 'Get user details by ID or name. Passwords are never exposed.'
    )]
    public function getUser(int|string $identifier): array
    {
        try {
            $user = $this->users()->get($identifier);

            if (!$user || $user instanceof NullPage || !$user->id) {
                return $this->error("User not found: {$identifier}", 'NOT_FOUND');
            }

            $result = $this->userToArray($user);

            // Add additional details for single user view
            $result['created'] = $user->created;
            $result['modified'] = $user->modified;

            // Get custom fields (excluding system fields and password)
            $customFields = [];
            foreach ($user->template->fieldgroup as $field) {
                $fieldName = $field->name;
                // Skip password and system fields
                if (in_array($fieldName, ['pass', 'roles', 'email', 'admin_theme'])) {
                    continue;
                }
                $customFields[$fieldName] = $this->getFieldValue($user, $fieldName);
            }

            if (!empty($customFields)) {
                $result['custom_fields'] = $customFields;
            }

            return $this->success($result);

        } catch (\Throwable $e) {
            return $this->error('Failed to get user: ' . $e->getMessage());
        }
    }

    /**
     * List all roles
     *
     * @return array Role list
     */
    #[McpTool(
        name: 'list_roles',
        description: 'List all user roles in the system.'
    )]
    public function listRoles(): array
    {
        try {
            $roles = $this->wire->wire('roles');
            $results = [];

            foreach ($roles as $role) {
                // Count users with this role
                $userCount = $this->users()->count("roles={$role->id}");

                // Get permissions
                $permissions = [];
                foreach ($role->permissions as $permission) {
                    $permissions[] = $permission->name;
                }

                $results[] = [
                    'id' => $role->id,
                    'name' => $role->name,
                    'user_count' => $userCount,
                    'permissions' => $permissions,
                ];
            }

            return $this->success([
                'count' => count($results),
                'roles' => $results,
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to list roles: ' . $e->getMessage());
        }
    }

    /**
     * Convert user to array (without password)
     *
     * @param User $user
     * @return array
     */
    private function userToArray(User $user): array
    {
        // Get role names
        $roles = [];
        foreach ($user->roles as $role) {
            if ($role->name !== 'guest') { // Exclude implicit guest role
                $roles[] = $role->name;
            }
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $roles,
            'is_superuser' => $user->isSuperuser(),
        ];
    }
}
