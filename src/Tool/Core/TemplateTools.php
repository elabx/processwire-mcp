<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tool\Core;

use Elabx\ProcessWireMcp\Tool\ProcessWireMcpTool;
use Mcp\Capability\Attribute\McpTool;

/**
 * Template management tools for ProcessWire MCP
 *
 * Provides tools for listing, inspecting, creating, updating, and deleting templates.
 */
class TemplateTools extends ProcessWireMcpTool
{
    public static function getToolInfo(): array
    {
        return [
            'name' => 'template_tools',
            'description' => 'Template inspection tools for ProcessWire',
            'priority' => 20,
        ];
    }

    /**
     * List all available templates
     *
     * @param bool $includeSystem Whether to include system templates (admin, user, etc.)
     * @return array Template list
     */
    #[McpTool(
        name: 'list_templates',
        description: 'List all available templates. Set includeSystem=true to include system templates.'
    )]
    public function listTemplates(bool $includeSystem = false): array
    {
        try {
            $templates = $this->templates();
            $results = [];

            foreach ($templates as $template) {
                // Skip system templates unless requested
                if (!$includeSystem && ($template->flags & \ProcessWire\Template::flagSystem)) {
                    continue;
                }

                $results[] = [
                    'id' => $template->id,
                    'name' => $template->name,
                    'label' => $template->label ?: $template->name,
                    'field_count' => $template->fieldgroup->count(),
                    'page_count' => $this->pages()->count("template={$template->name}"),
                    'is_system' => (bool) ($template->flags & \ProcessWire\Template::flagSystem),
                ];
            }

            // Sort by name
            usort($results, fn($a, $b) => strcasecmp($a['name'], $b['name']));

            return $this->success([
                'count' => count($results),
                'templates' => $results,
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to list templates: ' . $e->getMessage());
        }
    }

    /**
     * Get detailed information about a template and its fields
     *
     * @param string $template Template name
     * @return array Template details with fields
     */
    #[McpTool(
        name: 'get_template_fields',
        description: 'Get detailed information about a template including all its fields and their configurations.'
    )]
    public function getTemplateFields(string $template): array
    {
        try {
            $templateObj = $this->templates()->get($template);

            if (!$templateObj) {
                return $this->error("Template not found: {$template}", 'NOT_FOUND');
            }

            $fields = [];
            foreach ($templateObj->fieldgroup as $field) {
                $fieldData = [
                    'id' => $field->id,
                    'name' => $field->name,
                    'label' => $field->label ?: $field->name,
                    'type' => $field->type->className(),
                    'description' => $field->description,
                    'required' => (bool) $field->required,
                ];

                // Add type-specific info
                switch ($field->type->className()) {
                    case 'FieldtypePage':
                        $fieldData['settings'] = [
                            'template_id' => $field->template_id,
                            'parent_id' => $field->parent_id,
                            'derefAsPage' => $field->derefAsPage,
                        ];
                        break;

                    case 'FieldtypeOptions':
                        $options = [];
                        if ($field->type instanceof \ProcessWire\FieldtypeOptions) {
                            $mgr = $field->type->getOptions($field);
                            foreach ($mgr as $opt) {
                                $options[] = [
                                    'id' => $opt->id,
                                    'value' => $opt->value,
                                    'title' => $opt->title,
                                ];
                            }
                        }
                        $fieldData['options'] = $options;
                        break;

                    case 'FieldtypeText':
                    case 'FieldtypeTextarea':
                        $fieldData['settings'] = [
                            'maxlength' => $field->maxlength ?? null,
                            'contentType' => $field->contentType ?? 0,
                        ];
                        break;

                    case 'FieldtypeInteger':
                    case 'FieldtypeFloat':
                        $fieldData['settings'] = [
                            'min' => $field->min ?? null,
                            'max' => $field->max ?? null,
                        ];
                        break;

                    case 'FieldtypeImage':
                    case 'FieldtypeFile':
                        $fieldData['settings'] = [
                            'maxFiles' => $field->maxFiles ?? 0,
                            'extensions' => $field->extensions ?? '',
                        ];
                        break;
                }

                $fields[] = $fieldData;
            }

            // Get allowed parents and children templates
            $parentTemplates = [];
            if ($templateObj->parentTemplates) {
                foreach ($templateObj->parentTemplates as $parentId) {
                    $parent = $this->templates()->get($parentId);
                    if ($parent) {
                        $parentTemplates[] = $parent->name;
                    }
                }
            }

            $childTemplates = [];
            if ($templateObj->childTemplates) {
                foreach ($templateObj->childTemplates as $childId) {
                    $child = $this->templates()->get($childId);
                    if ($child) {
                        $childTemplates[] = $child->name;
                    }
                }
            }

            return $this->success([
                'id' => $templateObj->id,
                'name' => $templateObj->name,
                'label' => $templateObj->label ?: $templateObj->name,
                'is_system' => (bool) ($templateObj->flags & \ProcessWire\Template::flagSystem),
                'allowed_parents' => $parentTemplates,
                'allowed_children' => $childTemplates,
                'url_segment' => (bool) $templateObj->urlSegments,
                'https' => (bool) $templateObj->https,
                'field_count' => count($fields),
                'fields' => $fields,
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to get template fields: ' . $e->getMessage());
        }
    }

    /**
     * Get template file path if it exists
     *
     * @param string $template Template name
     * @return array Template file information
     */
    #[McpTool(
        name: 'get_template_file',
        description: 'Get the file path for a template if it exists.'
    )]
    public function getTemplateFile(string $template): array
    {
        try {
            $templateObj = $this->templates()->get($template);

            if (!$templateObj) {
                return $this->error("Template not found: {$template}", 'NOT_FOUND');
            }

            $config = $this->wire->wire('config');
            $templatesPath = $config->paths->templates;

            // Check for template file
            $filename = $templateObj->altFilename ?: $templateObj->name;
            $filePath = $templatesPath . $filename . '.php';

            $fileExists = file_exists($filePath);

            $result = [
                'template' => $templateObj->name,
                'filename' => $filename . '.php',
                'path' => $filePath,
                'exists' => $fileExists,
            ];

            if ($fileExists) {
                $result['size'] = filesize($filePath);
                $result['modified'] = filemtime($filePath);
            }

            return $this->success($result);

        } catch (\Throwable $e) {
            return $this->error('Failed to get template file: ' . $e->getMessage());
        }
    }

    /**
     * Create a new template
     *
     * @param string $name Template name
     * @param string|null $label Optional label
     * @param string|null $icon Optional icon
     * @return array Created template info
     */
    #[McpTool(
        name: 'create_template',
        description: 'Create a new template. Optionally set a label and icon.'
    )]
    public function createTemplate(string $name, ?string $label = null, ?string $icon = null): array
    {
        try {
            $sanitized = $this->sanitizer()->name($name);

            if (empty($sanitized)) {
                return $this->error("Invalid template name: {$name}");
            }

            if ($this->templates()->get($sanitized)) {
                return $this->error("Template already exists: {$sanitized}");
            }

            $t = $this->templates()->add($sanitized);

            if ($label !== null) {
                $t->label = $label;
            }

            if ($icon !== null) {
                $t->icon = $icon;
            }

            $t->save();

            return $this->success([
                'id' => $t->id,
                'name' => $t->name,
                'label' => $t->label ?: $t->name,
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to create template: ' . $e->getMessage());
        }
    }

    /**
     * Update template settings
     *
     * @param string $template Template name
     * @param string|null $label Optional label
     * @param string|null $icon Optional icon
     * @param array|null $parentTemplates Optional parent template restrictions (array of template names)
     * @param array|null $childTemplates Optional child template restrictions (array of template names)
     * @param bool|null $noParents Optional flag to disallow parents
     * @param bool|null $noChildren Optional flag to disallow children
     * @param string|null $sortfield Optional sort field
     * @param bool|null $urlSegments Optional URL segments toggle
     * @param bool|null $https Optional HTTPS mode toggle
     * @param int|null $cacheTime Optional cache time in seconds
     * @return array Updated template info
     */
    #[McpTool(
        name: 'update_template',
        description: 'Update template settings. Can set label, icon, parent/child template restrictions, sort field, URL segments, HTTPS mode, and cache time.'
    )]
    public function updateTemplate(
        string $template,
        ?string $label = null,
        ?string $icon = null,
        ?array $parentTemplates = null,
        ?array $childTemplates = null,
        ?bool $noParents = null,
        ?bool $noChildren = null,
        ?string $sortfield = null,
        ?bool $urlSegments = null,
        ?bool $https = null,
        ?int $cacheTime = null
    ): array {
        try {
            $templateObj = $this->templates()->get($template);

            if (!$templateObj) {
                return $this->error("Template not found: {$template}", 'NOT_FOUND');
            }

            if ($templateObj->flags & \ProcessWire\Template::flagSystem) {
                return $this->error("Cannot modify system template: {$template}");
            }

            if ($label !== null) {
                $templateObj->label = $label;
            }

            if ($icon !== null) {
                $templateObj->icon = $icon;
            }

            if ($parentTemplates !== null) {
                $ids = [];
                foreach ($parentTemplates as $name) {
                    $t = $this->templates()->get($name);
                    if ($t) {
                        $ids[] = $t->id;
                    }
                }
                $templateObj->parentTemplates = $ids;
            }

            if ($childTemplates !== null) {
                $ids = [];
                foreach ($childTemplates as $name) {
                    $t = $this->templates()->get($name);
                    if ($t) {
                        $ids[] = $t->id;
                    }
                }
                $templateObj->childTemplates = $ids;
            }

            if ($noParents !== null) {
                $templateObj->noParents = (int) $noParents;
            }

            if ($noChildren !== null) {
                $templateObj->noChildren = (int) $noChildren;
            }

            if ($sortfield !== null) {
                $templateObj->sortfield = $sortfield;
            }

            if ($urlSegments !== null) {
                $templateObj->urlSegments = $urlSegments;
            }

            if ($https !== null) {
                $templateObj->https = $https;
            }

            if ($cacheTime !== null) {
                $templateObj->cacheTime = $cacheTime;
            }

            $templateObj->save();

            return $this->success([
                'id' => $templateObj->id,
                'name' => $templateObj->name,
                'label' => $templateObj->label ?: $templateObj->name,
                'icon' => $templateObj->icon,
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to update template: ' . $e->getMessage());
        }
    }

    /**
     * Delete a template
     *
     * @param string $template Template name
     * @return array Deleted template info
     */
    #[McpTool(
        name: 'delete_template',
        description: 'Delete a template. The template must not have any pages using it and must not be a system template.'
    )]
    public function deleteTemplate(string $template): array
    {
        try {
            $templateObj = $this->templates()->get($template);

            if (!$templateObj) {
                return $this->error("Template not found: {$template}", 'NOT_FOUND');
            }

            if ($templateObj->flags & \ProcessWire\Template::flagSystem) {
                return $this->error("Cannot delete system template: {$template}");
            }

            $count = $this->pages()->count("template={$templateObj->name}");

            if ($count > 0) {
                return $this->error("Cannot delete template '{$template}': {$count} page(s) are using it");
            }

            $id = $templateObj->id;
            $name = $templateObj->name;

            $this->templates()->delete($templateObj);

            return $this->success([
                'id' => $id,
                'name' => $name,
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to delete template: ' . $e->getMessage());
        }
    }

    /**
     * Clone an existing template with a new name
     *
     * @param string $template Source template name
     * @param string $newName New template name
     * @return array Cloned template info
     */
    #[McpTool(
        name: 'clone_template',
        description: 'Clone an existing template with a new name. Copies all fields and settings from the source template.'
    )]
    public function cloneTemplate(string $template, string $newName): array
    {
        try {
            $templateObj = $this->templates()->get($template);

            if (!$templateObj) {
                return $this->error("Template not found: {$template}", 'NOT_FOUND');
            }

            $sanitizedName = $this->sanitizer()->name($newName);

            if (empty($sanitizedName)) {
                return $this->error("Invalid template name: {$newName}");
            }

            if ($this->templates()->get($sanitizedName)) {
                return $this->error("Template already exists: {$sanitizedName}");
            }

            $clone = $this->templates()->clone($templateObj);
            $clone->name = $sanitizedName;
            $clone->save();

            return $this->success([
                'id' => $clone->id,
                'name' => $clone->name,
                'source' => $templateObj->name,
                'field_count' => $clone->fieldgroup->count(),
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to clone template: ' . $e->getMessage());
        }
    }

    /**
     * Add a field to a template
     *
     * @param string $template Template name
     * @param string $field Field name
     * @param string|null $afterField Optional field name to position after
     * @param string|null $beforeField Optional field name to position before
     * @return array Result info
     */
    #[McpTool(
        name: 'add_field_to_template',
        description: 'Add a field to a template. Optionally position it after or before another field.'
    )]
    public function addFieldToTemplate(
        string $template,
        string $field,
        ?string $afterField = null,
        ?string $beforeField = null
    ): array {
        try {
            $templateObj = $this->templates()->get($template);

            if (!$templateObj) {
                return $this->error("Template not found: {$template}", 'NOT_FOUND');
            }

            $fieldObj = $this->fields()->get($field);

            if (!$fieldObj) {
                return $this->error("Field not found: {$field}", 'NOT_FOUND');
            }

            if ($templateObj->fieldgroup->hasField($fieldObj)) {
                return $this->error("Field '{$field}' is already in template '{$template}'");
            }

            $templateObj->fieldgroup->add($fieldObj);

            if ($afterField !== null) {
                $afterFieldObj = $this->fields()->get($afterField);
                if ($afterFieldObj) {
                    $templateObj->fieldgroup->insertAfter($fieldObj, $afterFieldObj);
                }
            }

            if ($beforeField !== null) {
                $beforeFieldObj = $this->fields()->get($beforeField);
                if ($beforeFieldObj) {
                    $templateObj->fieldgroup->insertBefore($fieldObj, $beforeFieldObj);
                }
            }

            $templateObj->fieldgroup->save();

            return $this->success([
                'template' => $templateObj->name,
                'field' => $fieldObj->name,
                'field_count' => $templateObj->fieldgroup->count(),
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to add field to template: ' . $e->getMessage());
        }
    }

    /**
     * Remove a field from a template
     *
     * @param string $template Template name
     * @param string $field Field name
     * @return array Result info
     */
    #[McpTool(
        name: 'remove_field_from_template',
        description: 'Remove a field from a template.'
    )]
    public function removeFieldFromTemplate(string $template, string $field): array
    {
        try {
            $templateObj = $this->templates()->get($template);

            if (!$templateObj) {
                return $this->error("Template not found: {$template}", 'NOT_FOUND');
            }

            $fieldObj = $this->fields()->get($field);

            if (!$fieldObj) {
                return $this->error("Field not found: {$field}", 'NOT_FOUND');
            }

            if (!$templateObj->fieldgroup->hasField($fieldObj)) {
                return $this->error("Field '{$field}' is not in template '{$template}'");
            }

            $templateObj->fieldgroup->remove($fieldObj);
            $templateObj->fieldgroup->save();

            return $this->success([
                'template' => $templateObj->name,
                'field' => $fieldObj->name,
                'field_count' => $templateObj->fieldgroup->count(),
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to remove field from template: ' . $e->getMessage());
        }
    }
}
