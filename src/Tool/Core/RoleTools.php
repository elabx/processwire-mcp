<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tool\Core;

use Elabx\ProcessWireMcp\Tool\ProcessWireMcpTool;
use Mcp\Capability\Attribute\McpTool;

/**
 * Role and permission management tools for ProcessWire MCP
 *
 * Provides tools for creating, updating, and deleting roles and permissions,
 * as well as setting role-based access control on templates.
 */
class RoleTools extends ProcessWireMcpTool
{
    public static function getToolInfo(): array
    {
        return [
            'name' => 'role_tools',
            'description' => 'Role and permission management tools for ProcessWire',
            'priority' => 45,
        ];
    }

    /**
     * Create a new role with optional permissions
     *
     * @param string $name Role name
     * @param array $permissions List of permission names to assign
     * @return array Created role info
     */
    #[McpTool(
        name: 'create_role',
        description: 'Create a new role with optional permissions.'
    )]
    public function createRole(string $name, array $permissions = []): array
    {
        try {
            $sanitized = $this->sanitizer()->pageName($name);
            if (empty($sanitized)) {
                return $this->error("Invalid role name: {$name}");
            }

            $existing = $this->roles()->get($sanitized);
            if ($existing && $existing->id) {
                return $this->error("Role already exists: {$sanitized}");
            }

            $role = $this->roles()->add($sanitized);

            $addedPermissions = [];
            foreach ($permissions as $pName) {
                $permObj = $this->permissions()->get($pName);
                if ($permObj && $permObj->id) {
                    $role->addPermission($permObj);
                    $addedPermissions[] = $permObj->name;
                }
            }

            $role->save();

            return $this->success([
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $addedPermissions,
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to create role: ' . $e->getMessage());
        }
    }

    /**
     * Update a role by adding or removing permissions
     *
     * @param string $role Role name or ID
     * @param array $addPermissions Permission names to add
     * @param array $removePermissions Permission names to remove
     * @return array Updated role info
     */
    #[McpTool(
        name: 'update_role',
        description: 'Update a role by adding or removing permissions. Cannot modify guest or superuser roles.'
    )]
    public function updateRole(string $role, array $addPermissions = [], array $removePermissions = []): array
    {
        try {
            $roleObj = $this->roles()->get($role);
            if (!$roleObj || !$roleObj->id) {
                return $this->error("Role not found: {$role}", 'NOT_FOUND');
            }

            if (in_array($roleObj->name, ['guest', 'superuser'])) {
                return $this->error("Cannot modify the '{$roleObj->name}' role.");
            }

            foreach ($addPermissions as $pName) {
                $permObj = $this->permissions()->get($pName);
                if ($permObj && $permObj->id) {
                    $roleObj->addPermission($permObj);
                }
            }

            foreach ($removePermissions as $pName) {
                $permObj = $this->permissions()->get($pName);
                if ($permObj && $permObj->id) {
                    $roleObj->removePermission($permObj);
                }
            }

            $roleObj->save();

            // Collect current permissions
            $currentPermissions = [];
            foreach ($roleObj->permissions as $perm) {
                $currentPermissions[] = $perm->name;
            }
            sort($currentPermissions);

            return $this->success([
                'id' => $roleObj->id,
                'name' => $roleObj->name,
                'permissions' => $currentPermissions,
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to update role: ' . $e->getMessage());
        }
    }

    /**
     * Delete a role
     *
     * @param string $role Role name or ID
     * @return array Deleted role info
     */
    #[McpTool(
        name: 'delete_role',
        description: 'Delete a role. Cannot delete guest or superuser roles. The role must not be assigned to any users.'
    )]
    public function deleteRole(string $role): array
    {
        try {
            $roleObj = $this->roles()->get($role);
            if (!$roleObj || !$roleObj->id) {
                return $this->error("Role not found: {$role}", 'NOT_FOUND');
            }

            if (in_array($roleObj->name, ['guest', 'superuser'])) {
                return $this->error("Cannot delete the '{$roleObj->name}' role.");
            }

            $userCount = $this->users()->count("roles={$roleObj->id}");
            if ($userCount > 0) {
                return $this->error(
                    "Cannot delete role '{$roleObj->name}' because it is assigned to {$userCount} user(s)."
                );
            }

            $deletedInfo = [
                'id' => $roleObj->id,
                'name' => $roleObj->name,
            ];

            $this->roles()->delete($roleObj);

            return $this->success($deletedInfo);

        } catch (\Throwable $e) {
            return $this->error('Failed to delete role: ' . $e->getMessage());
        }
    }

    /**
     * List all permissions available in the system
     *
     * @return array Permissions list
     */
    #[McpTool(
        name: 'list_permissions',
        description: 'List all permissions available in the system.'
    )]
    public function listPermissions(): array
    {
        try {
            $results = [];

            foreach ($this->permissions() as $perm) {
                $results[] = [
                    'id' => $perm->id,
                    'name' => $perm->name,
                    'title' => $perm->title ?: $perm->name,
                ];
            }

            // Sort by name
            usort($results, fn($a, $b) => strcasecmp($a['name'], $b['name']));

            return $this->success([
                'count' => count($results),
                'permissions' => $results,
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to list permissions: ' . $e->getMessage());
        }
    }

    /**
     * Create a new permission
     *
     * @param string $name Permission name
     * @param string|null $title Permission title
     * @return array Created permission info
     */
    #[McpTool(
        name: 'create_permission',
        description: 'Create a new permission.'
    )]
    public function createPermission(string $name, ?string $title = null): array
    {
        try {
            $sanitized = $this->sanitizer()->pageName($name);
            if (empty($sanitized)) {
                return $this->error("Invalid permission name: {$name}");
            }

            $existing = $this->permissions()->get($sanitized);
            if ($existing && $existing->id) {
                return $this->error("Permission already exists: {$sanitized}");
            }

            $perm = $this->permissions()->add($sanitized);

            if ($title !== null) {
                $perm->title = $title;
            }

            $perm->save();

            return $this->success([
                'id' => $perm->id,
                'name' => $perm->name,
                'title' => $perm->title ?: $perm->name,
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to create permission: ' . $e->getMessage());
        }
    }

    /**
     * Set role-based access control on a template
     *
     * @param string $template Template name
     * @param string $role Role name
     * @param string $accessType Access type: view, edit, create, or add
     * @param string $action Action: grant or revoke
     * @return array Result info
     */
    #[McpTool(
        name: 'set_template_access',
        description: 'Set role-based access control on a template. Access types: view, edit, create, add. Actions: grant or revoke.'
    )]
    public function setTemplateAccess(string $template, string $role, string $accessType, string $action): array
    {
        try {
            $templateObj = $this->templates()->get($template);
            if (!$templateObj || !$templateObj->id) {
                return $this->error("Template not found: {$template}", 'NOT_FOUND');
            }

            $roleObj = $this->roles()->get($role);
            if (!$roleObj || !$roleObj->id) {
                return $this->error("Role not found: {$role}", 'NOT_FOUND');
            }

            if (!in_array($accessType, ['view', 'edit', 'create', 'add'])) {
                return $this->error(
                    "Invalid access type: {$accessType}. Must be one of: view, edit, create, add."
                );
            }

            if (!in_array($action, ['grant', 'revoke'])) {
                return $this->error(
                    "Invalid action: {$action}. Must be one of: grant, revoke."
                );
            }

            // Enable role-based access on the template if not already enabled
            if (!$templateObj->useRoles) {
                $templateObj->useRoles = 1;
            }

            if ($action === 'grant') {
                if ($accessType === 'view') {
                    $templateObj->addRole($roleObj);
                } else {
                    $templateObj->addRole($roleObj, $accessType);
                }
            } else {
                if ($accessType === 'view') {
                    $templateObj->removeRole($roleObj);
                } else {
                    $templateObj->removeRole($roleObj, $accessType);
                }
            }

            $templateObj->save();

            return $this->success([
                'template' => $templateObj->name,
                'role' => $roleObj->name,
                'access_type' => $accessType,
                'action' => $action,
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to set template access: ' . $e->getMessage());
        }
    }
}
