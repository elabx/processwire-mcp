<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tool\Core;

use Elabx\ProcessWireMcp\Tool\ProcessWireMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use ProcessWire\Page;
use ProcessWire\NullPage;

/**
 * Core page management tools for ProcessWire MCP
 *
 * Provides tools for finding, getting, creating, updating, and deleting pages.
 */
class PageTools extends ProcessWireMcpTool
{
    public static function getToolInfo(): array
    {
        return [
            'name' => 'page_tools',
            'description' => 'Core page management tools for ProcessWire',
            'priority' => 10,
        ];
    }

    /**
     * Find pages using ProcessWire selectors
     *
     * @param string $selector ProcessWire selector string (e.g., "template=basic-page, limit=10")
     * @param array $fields Optional list of field names to include in results
     * @return array Search results
     */
    #[McpTool(
        name: 'find_pages',
        description: 'Find pages using ProcessWire selector syntax. Example: "template=basic-page, limit=10". Admin pages are excluded for security.'
    )]
    public function findPages(string $selector, array $fields = []): array
    {
        try {
            // Add admin exclusion to selector
            $safeSelector = $this->excludeAdminFromSelector($selector);

            $pages = $this->pages()->find($safeSelector);

            $results = [];
            foreach ($pages as $page) {
                $results[] = $this->pageToArray($page, $fields);
            }

            return $this->success([
                'count' => $pages->count(),
                'total' => $pages->getTotal(),
                'pages' => $results,
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to find pages: ' . $e->getMessage());
        }
    }

    /**
     * Get a single page by ID or path
     *
     * @param string|int $identifier Page ID or path
     * @param array $fields Optional list of field names to include
     * @return array Page data
     */
    #[McpTool(
        name: 'get_page',
        description: 'Get a single page by ID or path. Example: get_page(1) or get_page("/about/"). Admin pages are blocked.'
    )]
    public function getPage(string|int $identifier, array $fields = []): array
    {
        try {
            $page = $this->pages()->get($identifier);

            if (!$page || $page instanceof NullPage || !$page->id) {
                return $this->error("Page not found: {$identifier}", 'NOT_FOUND');
            }

            // Security: Block admin pages
            $this->assertNotAdminPage($page);

            // If no specific fields requested, include all template fields
            if (empty($fields)) {
                $fields = [];
                foreach ($page->template->fieldgroup as $field) {
                    $fields[] = $field->name;
                }
            }

            return $this->success($this->pageToArray($page, $fields));

        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'ACCESS_DENIED');
        } catch (\Throwable $e) {
            return $this->error('Failed to get page: ' . $e->getMessage());
        }
    }

    /**
     * Create a new page
     *
     * @param string $template Template name
     * @param int|string $parent Parent page ID or path
     * @param string $title Page title
     * @param string|null $name Optional page name (URL segment). Auto-generated from title if not provided.
     * @param array $fieldValues Optional associative array of field values
     * @return array Created page data
     */
    #[McpTool(
        name: 'create_page',
        description: 'Create a new page. Requires template, parent (ID or path), and title. Cannot create admin pages.'
    )]
    public function createPage(
        string $template,
        int|string $parent,
        string $title,
        ?string $name = null,
        #[Schema(type: 'object', description: 'Optional object of field name => value pairs', additionalProperties: true)]
        array $fieldValues = []
    ): array {
        try {
            // Get parent page
            $parentPage = $this->pages()->get($parent);

            if (!$parentPage || $parentPage instanceof NullPage || !$parentPage->id) {
                return $this->error("Parent page not found: {$parent}", 'NOT_FOUND');
            }

            // Security: Cannot create pages under admin
            $this->assertNotAdminPage($parentPage);

            // Get template
            $templateObj = $this->templates()->get($template);
            if (!$templateObj) {
                return $this->error("Template not found: {$template}", 'NOT_FOUND');
            }

            // Create the page
            $page = new Page();
            $page->template = $templateObj;
            $page->parent = $parentPage;
            $page->title = $title;

            // Set name (sanitized)
            if ($name) {
                $page->name = $this->sanitizer()->pageName($name);
            }

            // Set additional field values
            foreach ($fieldValues as $fieldName => $value) {
                if ($page->template->fieldgroup->hasField($fieldName)) {
                    $page->set($fieldName, $value);
                }
            }

            // Save the page
            $page->save();

            return $this->success(
                $this->pageToArray($page),
                "Page created successfully with ID {$page->id}"
            );

        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'ACCESS_DENIED');
        } catch (\Throwable $e) {
            return $this->error('Failed to create page: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing page
     *
     * @param int|string $identifier Page ID or path
     * @param array $fieldValues Associative array of field names and values to update
     * @return array Updated page data
     */
    #[McpTool(
        name: 'update_page',
        description: 'Update page field values. Provide page ID/path and an array of field => value pairs. Cannot update admin pages.'
    )]
    public function updatePage(
        int|string $identifier,
        #[Schema(type: 'object', description: 'Object of field name => value pairs to update', additionalProperties: true)]
        array $fieldValues
    ): array
    {
        try {
            $page = $this->pages()->get($identifier);

            if (!$page || $page instanceof NullPage || !$page->id) {
                return $this->error("Page not found: {$identifier}", 'NOT_FOUND');
            }

            // Security: Block admin pages
            $this->assertNotAdminPage($page);

            if (empty($fieldValues)) {
                return $this->error('No field values provided to update', 'INVALID_INPUT');
            }

            $updated = [];
            $skipped = [];

            foreach ($fieldValues as $fieldName => $value) {
                // Handle special properties
                if ($fieldName === 'name') {
                    $page->name = $this->sanitizer()->pageName($value);
                    $updated[] = 'name';
                    continue;
                }

                if ($fieldName === 'title') {
                    $page->title = $value;
                    $updated[] = 'title';
                    continue;
                }

                if ($fieldName === 'status') {
                    $page->status = (int) $value;
                    $updated[] = 'status';
                    continue;
                }

                if ($fieldName === 'parent') {
                    $newParent = $this->pages()->get($value);
                    if ($newParent && $newParent->id) {
                        $this->assertNotAdminPage($newParent);
                        $page->parent = $newParent;
                        $updated[] = 'parent';
                    } else {
                        $skipped[] = "parent (not found: {$value})";
                    }
                    continue;
                }

                // Check if field exists in template
                if (!$page->template->fieldgroup->hasField($fieldName)) {
                    $skipped[] = "{$fieldName} (not in template)";
                    continue;
                }

                $page->set($fieldName, $value);
                $updated[] = $fieldName;
            }

            // Save changes
            $page->save();

            $result = $this->pageToArray($page);
            $result['_updated_fields'] = $updated;

            if (!empty($skipped)) {
                $result['_skipped_fields'] = $skipped;
            }

            return $this->success($result, 'Page updated successfully');

        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'ACCESS_DENIED');
        } catch (\Throwable $e) {
            return $this->error('Failed to update page: ' . $e->getMessage());
        }
    }

    /**
     * Delete (trash) a page
     *
     * @param int|string $identifier Page ID or path
     * @param bool $permanent If true, permanently delete instead of trashing
     * @return array Deletion result
     */
    #[McpTool(
        name: 'delete_page',
        description: 'Delete a page. By default moves to trash. Set permanent=true to permanently delete. Cannot delete admin pages or system pages.'
    )]
    public function deletePage(int|string $identifier, bool $permanent = false): array
    {
        try {
            $page = $this->pages()->get($identifier);

            if (!$page || $page instanceof NullPage || !$page->id) {
                return $this->error("Page not found: {$identifier}", 'NOT_FOUND');
            }

            // Security: Block admin pages
            $this->assertNotAdminPage($page);

            // Block system pages (home, trash, etc.)
            if ($page->id <= 7) {
                return $this->error(
                    'Cannot delete system pages (home, trash, etc.)',
                    'ACCESS_DENIED'
                );
            }

            $pageId = $page->id;
            $pagePath = $page->path;

            if ($permanent) {
                // Permanently delete
                $this->pages()->delete($page, true);
                return $this->success([
                    'id' => $pageId,
                    'path' => $pagePath,
                    'action' => 'permanently_deleted',
                ], 'Page permanently deleted');
            } else {
                // Move to trash
                $this->pages()->trash($page);
                return $this->success([
                    'id' => $pageId,
                    'path' => $pagePath,
                    'action' => 'trashed',
                ], 'Page moved to trash');
            }

        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'ACCESS_DENIED');
        } catch (\Throwable $e) {
            return $this->error('Failed to delete page: ' . $e->getMessage());
        }
    }

    /**
     * Get child pages of a parent
     *
     * @param int|string $parent Parent page ID or path
     * @param string $selector Optional additional selector filters
     * @param array $fields Optional list of field names to include
     * @return array Child pages
     */
    #[McpTool(
        name: 'get_children',
        description: 'Get child pages of a parent page. Optionally filter with a selector.'
    )]
    public function getChildren(
        int|string $parent,
        string $selector = '',
        array $fields = []
    ): array {
        try {
            $parentPage = $this->pages()->get($parent);

            if (!$parentPage || $parentPage instanceof NullPage || !$parentPage->id) {
                return $this->error("Parent page not found: {$parent}", 'NOT_FOUND');
            }

            // Security: Block admin pages
            $this->assertNotAdminPage($parentPage);

            $safeSelector = $this->excludeAdminFromSelector($selector);
            $children = $parentPage->children($safeSelector);

            $results = [];
            foreach ($children as $child) {
                $results[] = $this->pageToArray($child, $fields);
            }

            return $this->success([
                'parent' => [
                    'id' => $parentPage->id,
                    'title' => $parentPage->title,
                    'path' => $parentPage->path,
                ],
                'count' => count($results),
                'children' => $results,
            ]);

        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'ACCESS_DENIED');
        } catch (\Throwable $e) {
            return $this->error('Failed to get children: ' . $e->getMessage());
        }
    }
}
