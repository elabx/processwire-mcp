<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tool\Core;

use Elabx\ProcessWireMcp\Tool\ProcessWireMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use ProcessWire\User;
use ProcessWire\NullPage;

/**
 * User management tools for ProcessWire MCP
 *
 * Provides tools for managing users.
 * Note: Passwords are NEVER exposed for security.
 */
class UserTools extends ProcessWireMcpTool
{
    public static function getToolInfo(): array
    {
        return [
            'name' => 'user_tools',
            'description' => 'User management tools for ProcessWire',
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
     * Create a new user
     *
     * @param string $name Username
     * @param string $email User email
     * @param string $password User password
     * @param array $roles Role names to assign
     * @return array Created user data
     */
    #[McpTool(
        name: 'create_user',
        description: 'Create a new user with email, password, and roles. Password is never returned in the response.'
    )]
    public function createUser(
        string $name,
        string $email,
        string $password,
        array $roles = []
    ): array {
        try {
            $sanitized = $this->sanitizer()->pageName($name);
            if (empty($sanitized)) {
                return $this->error("Invalid username: {$name}");
            }

            $sanitizedEmail = $this->sanitizer()->email($email);
            if (empty($sanitizedEmail)) {
                return $this->error("Invalid email: {$email}");
            }

            $existing = $this->users()->get("name=$sanitized");
            if ($existing && $existing->id) {
                return $this->error("User already exists: {$sanitized}");
            }

            $user = new User();
            $user->name = $sanitized;
            $user->pass = $password;
            $user->email = $sanitizedEmail;

            foreach ($roles as $roleName) {
                $roleObj = $this->wire->wire('roles')->get($roleName);
                if ($roleObj && $roleObj->id) {
                    $user->addRole($roleObj);
                }
            }

            $user->save();

            return $this->success([
                'message' => "User created: {$sanitized}",
                'user' => $this->userToArray($user),
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to create user: ' . $e->getMessage());
        }
    }

    /**
     * Update a user
     *
     * @param int|string $identifier User ID or name
     * @param string|null $email New email
     * @param string|null $password New password
     * @param array $addRoles Roles to add
     * @param array $removeRoles Roles to remove
     * @param array $fieldValues Custom field values to set
     * @return array Updated user data
     */
    #[McpTool(
        name: 'update_user',
        description: 'Update a user. Can change email, password, add/remove roles, and set custom field values. Password is never returned.'
    )]
    public function updateUser(
        int|string $identifier,
        ?string $email = null,
        ?string $password = null,
        array $addRoles = [],
        array $removeRoles = [],
        #[Schema(type: 'object', description: 'Custom field values to set (excludes pass, roles, email)', additionalProperties: true)]
        array $fieldValues = []
    ): array {
        try {
            $user = $this->users()->get($identifier);

            if (!$user || $user instanceof NullPage || !$user->id) {
                return $this->error("User not found: {$identifier}", 'NOT_FOUND');
            }

            if ($email !== null) {
                $sanitizedEmail = $this->sanitizer()->email($email);
                if (empty($sanitizedEmail)) {
                    return $this->error("Invalid email: {$email}");
                }
                $user->email = $sanitizedEmail;
            }

            if ($password !== null) {
                $user->pass = $password;
            }

            foreach ($addRoles as $roleName) {
                $roleObj = $this->wire->wire('roles')->get($roleName);
                if ($roleObj && $roleObj->id) {
                    $user->addRole($roleObj);
                }
            }

            foreach ($removeRoles as $roleName) {
                $roleObj = $this->wire->wire('roles')->get($roleName);
                if ($roleObj && $roleObj->id) {
                    $user->removeRole($roleObj);
                }
            }

            foreach ($fieldValues as $key => $value) {
                if (in_array($key, ['pass', 'roles', 'email'])) {
                    continue;
                }
                if ($user->template->fieldgroup->hasField($key)) {
                    $user->set($key, $value);
                }
            }

            $user->save();

            return $this->success([
                'message' => "User updated: {$user->name}",
                'user' => $this->userToArray($user),
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to update user: ' . $e->getMessage());
        }
    }

    /**
     * Delete a user
     *
     * @param int|string $identifier User ID or name
     * @return array Deletion result
     */
    #[McpTool(
        name: 'delete_user',
        description: 'Delete a user. Cannot delete the superuser or guest user.'
    )]
    public function deleteUser(int|string $identifier): array
    {
        try {
            $user = $this->users()->get($identifier);

            if (!$user || $user instanceof NullPage || !$user->id) {
                return $this->error("User not found: {$identifier}", 'NOT_FOUND');
            }

            if ($user->isSuperuser()) {
                return $this->error('Cannot delete superuser');
            }

            if ($user->name === 'guest') {
                return $this->error('Cannot delete guest user');
            }

            $deletedInfo = [
                'id' => $user->id,
                'name' => $user->name,
            ];

            $this->users()->delete($user);

            return $this->success([
                'message' => "User deleted: {$deletedInfo['name']}",
                'deleted_user' => $deletedInfo,
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to delete user: ' . $e->getMessage());
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
