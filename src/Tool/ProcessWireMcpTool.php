<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tool;

use ProcessWire\ProcessWire;
use ProcessWire\Pages;
use ProcessWire\Templates;
use ProcessWire\Fields;
use ProcessWire\Users;
use ProcessWire\Roles;
use ProcessWire\Permissions;
use ProcessWire\Modules;
use ProcessWire\Sanitizer;
use ProcessWire\Page;

/**
 * Base class for all ProcessWire MCP tools
 *
 * Third-party ProcessWire modules can extend this class to register
 * custom MCP tools. Tools are discovered via PHP 8 attributes.
 *
 * Example:
 * ```php
 * class MyCustomTools extends ProcessWireMcpTool
 * {
 *     public static function getToolInfo(): array
 *     {
 *         return [
 *             'name' => 'my_custom_tools',
 *             'description' => 'Custom tools for my module',
 *             'priority' => 100,
 *         ];
 *     }
 *
 *     #[McpTool(name: 'my_operation', description: 'Does something custom')]
 *     public function myOperation(string $param): array
 *     {
 *         return ['result' => $this->pages()->count($param)];
 *     }
 * }
 * ```
 */
abstract class ProcessWireMcpTool
{
    protected ProcessWire $wire;

    /**
     * Admin page ID - access is blocked to this page and descendants
     */
    protected const ADMIN_PAGE_ID = 2;

    /**
     * Get tool metadata for registration
     *
     * Override this in subclasses to provide tool information.
     *
     * @return array{name: string, description: string, priority?: int}
     */
    public static function getToolInfo(): array
    {
        return [
            'name' => '',
            'description' => '',
            'priority' => 100, // Load order: lower = earlier
        ];
    }

    /**
     * Set the ProcessWire instance
     *
     * Called by the server during tool registration.
     */
    public function setWire(ProcessWire $wire): void
    {
        $this->wire = $wire;
    }

    /**
     * Get the ProcessWire instance
     */
    protected function wire(): ProcessWire
    {
        return $this->wire;
    }

    /**
     * Get the Pages API
     */
    protected function pages(): Pages
    {
        return $this->wire->wire('pages');
    }

    /**
     * Get the Templates API
     */
    protected function templates(): Templates
    {
        return $this->wire->wire('templates');
    }

    /**
     * Get the Fields API
     */
    protected function fields(): Fields
    {
        return $this->wire->wire('fields');
    }

    /**
     * Get the Users API
     */
    protected function users(): Users
    {
        return $this->wire->wire('users');
    }

    /**
     * Get the Roles API
     */
    protected function roles(): Roles
    {
        return $this->wire->wire('roles');
    }

    /**
     * Get the Permissions API
     */
    protected function permissions(): Permissions
    {
        return $this->wire->wire('permissions');
    }

    /**
     * Get the Modules API
     */
    protected function modules(): Modules
    {
        return $this->wire->wire('modules');
    }

    /**
     * Get the Sanitizer API
     */
    protected function sanitizer(): Sanitizer
    {
        return $this->wire->wire('sanitizer');
    }

    /**
     * Get any wire service by name
     */
    protected function get(string $name): mixed
    {
        return $this->wire->wire($name);
    }

    /**
     * Check if a page is in the admin tree
     *
     * @param Page|int $page Page object or ID
     * @return bool True if the page is admin or a descendant of admin
     */
    protected function isAdminPage(Page|int $page): bool
    {
        if (is_int($page)) {
            $page = $this->pages()->get($page);
        }

        if (!$page || !$page->id) {
            return false;
        }

        // Check if it's the admin page itself
        if ($page->id === self::ADMIN_PAGE_ID) {
            return true;
        }

        // Check if it's a descendant of admin
        $adminPage = $this->pages()->get(self::ADMIN_PAGE_ID);
        return $page->parents()->has($adminPage);
    }

    /**
     * Validate that a page is not in the admin tree
     *
     * @param Page|int $page Page object or ID
     * @throws \InvalidArgumentException If the page is in the admin tree
     */
    protected function assertNotAdminPage(Page|int $page): void
    {
        if ($this->isAdminPage($page)) {
            throw new \InvalidArgumentException(
                'Access to admin pages is not permitted for security reasons.'
            );
        }
    }

    /**
     * Check if a page is a repeater page
     */
    protected function isRepeaterPage(Page $page): bool
    {
        return str_starts_with($page->template->name, 'repeater_');
    }

    /**
     * Assert that the content page (the page owning this content) is not an admin page.
     *
     * For repeater pages, traces back to the content page via getForPage().
     * For regular pages, falls through to assertNotAdminPage().
     */
    protected function assertContentPageNotAdmin(Page $page): void
    {
        if ($this->isRepeaterPage($page)) {
            $contentPage = $page->getForPage();
            if ($contentPage && $contentPage->id) {
                $this->assertNotAdminPage($contentPage);
            }
        } else {
            $this->assertNotAdminPage($page);
        }
    }

    /**
     * Add admin exclusion to a selector string
     *
     * @param string $selector Original selector
     * @return string Modified selector excluding admin pages
     */
    protected function excludeAdminFromSelector(string $selector): string
    {
        // Exclude admin page and all its descendants
        $adminExclusion = "has_parent!=" . self::ADMIN_PAGE_ID . ", id!=" . self::ADMIN_PAGE_ID;

        if (empty(trim($selector))) {
            return $adminExclusion;
        }

        return $selector . ", " . $adminExclusion;
    }

    /**
     * Convert a Page to an array representation
     *
     * @param Page $page The page to convert
     * @param array $fields Optional list of fields to include (empty = basic info only)
     * @return array Page data as array
     */
    protected function pageToArray(Page $page, array $fields = []): array
    {
        $data = [
            'id' => $page->id,
            'name' => $page->name,
            'title' => $page->title,
            'path' => $page->path,
            'url' => $page->url,
            'template' => $page->template->name,
            'parent_id' => $page->parent_id,
            'created' => $page->created,
            'modified' => $page->modified,
            'status' => $page->status,
            'is_published' => !$page->isUnpublished(),
            'is_hidden' => $page->isHidden(),
        ];

        // Include requested field values
        if (!empty($fields)) {
            $data['fields'] = [];
            foreach ($fields as $fieldName) {
                $field = $page->template->fieldgroup->getField($fieldName);
                if ($field) {
                    $data['fields'][$fieldName] = $this->getFieldValue($page, $fieldName);
                }
            }
        }

        return $data;
    }

    /**
     * Get a field value from a page in a serializable format
     *
     * @param Page $page The page
     * @param string $fieldName The field name
     * @return mixed The field value
     */
    protected function getFieldValue(Page $page, string $fieldName): mixed
    {
        $value = $page->get($fieldName);

        if ($value === null) {
            return null;
        }

        // Handle PageArray
        if ($value instanceof \ProcessWire\PageArray) {
            return array_map(fn($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'path' => $p->path,
            ], $value->getArray());
        }

        // Handle single Page reference
        if ($value instanceof Page) {
            return [
                'id' => $value->id,
                'title' => $value->title,
                'path' => $value->path,
            ];
        }

        // Handle file/image fields
        if ($value instanceof \ProcessWire\Pagefiles || $value instanceof \ProcessWire\Pageimages) {
            return array_map(fn($f) => [
                'name' => $f->name,
                'url' => $f->url,
                'description' => $f->description,
                'filesize' => $f->filesize,
            ], $value->getArray());
        }

        // Handle WireArray generically
        if ($value instanceof \ProcessWire\WireArray) {
            return $value->getArray();
        }

        // Scalar values pass through
        if (is_scalar($value)) {
            return $value;
        }

        // Arrays pass through
        if (is_array($value)) {
            return $value;
        }

        // For objects, try to convert to string
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        // Default: return type info for unknown types
        return '[' . get_class($value) . ']';
    }

    /**
     * Format a success response
     */
    protected function success(mixed $data, string $message = ''): array
    {
        $response = ['success' => true];

        if ($message) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        return $response;
    }

    /**
     * Format an error response
     */
    protected function error(string $message, ?string $code = null): array
    {
        $response = [
            'success' => false,
            'error' => $message,
        ];

        if ($code) {
            $response['code'] = $code;
        }

        return $response;
    }
}
