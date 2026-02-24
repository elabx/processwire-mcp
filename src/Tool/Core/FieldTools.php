<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tool\Core;

use Elabx\ProcessWireMcp\Tool\ProcessWireMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

/**
 * Field management tools for ProcessWire MCP
 *
 * Provides tools for listing, inspecting, creating, updating, and deleting fields.
 */
class FieldTools extends ProcessWireMcpTool
{
    public static function getToolInfo(): array
    {
        return [
            'name' => 'field_tools',
            'description' => 'Field inspection tools for ProcessWire',
            'priority' => 30,
        ];
    }

    /**
     * List all fields
     *
     * @param bool $includeSystem Whether to include system fields
     * @param string|null $type Filter by field type (e.g., "FieldtypeText")
     * @return array Field list
     */
    #[McpTool(
        name: 'list_fields',
        description: 'List all fields in the system. Optionally filter by type.'
    )]
    public function listFields(bool $includeSystem = false, ?string $type = null): array
    {
        try {
            $fields = $this->fields();
            $results = [];

            foreach ($fields as $field) {
                // Skip system fields unless requested
                if (!$includeSystem && ($field->flags & \ProcessWire\Field::flagSystem)) {
                    continue;
                }

                // Filter by type if specified
                $fieldType = $field->type->className();
                if ($type !== null && stripos($fieldType, $type) === false) {
                    continue;
                }

                // Count templates using this field
                $templateCount = 0;
                foreach ($this->templates() as $template) {
                    if ($template->fieldgroup->hasField($field)) {
                        $templateCount++;
                    }
                }

                $results[] = [
                    'id' => $field->id,
                    'name' => $field->name,
                    'label' => $field->label ?: $field->name,
                    'type' => $fieldType,
                    'is_system' => (bool) ($field->flags & \ProcessWire\Field::flagSystem),
                    'template_count' => $templateCount,
                ];
            }

            // Sort by name
            usort($results, fn($a, $b) => strcasecmp($a['name'], $b['name']));

            return $this->success([
                'count' => count($results),
                'fields' => $results,
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to list fields: ' . $e->getMessage());
        }
    }

    /**
     * Get detailed field information
     *
     * @param string $field Field name
     * @return array Field details
     */
    #[McpTool(
        name: 'get_field',
        description: 'Get detailed information about a field including its configuration and which templates use it.'
    )]
    public function getField(string $field): array
    {
        try {
            $fieldObj = $this->fields()->get($field);

            if (!$fieldObj) {
                return $this->error("Field not found: {$field}", 'NOT_FOUND');
            }

            // Get templates using this field
            $templates = [];
            foreach ($this->templates() as $template) {
                if ($template->fieldgroup->hasField($fieldObj)) {
                    $templates[] = [
                        'id' => $template->id,
                        'name' => $template->name,
                    ];
                }
            }

            $result = [
                'id' => $fieldObj->id,
                'name' => $fieldObj->name,
                'label' => $fieldObj->label ?: $fieldObj->name,
                'description' => $fieldObj->description,
                'type' => $fieldObj->type->className(),
                'is_system' => (bool) ($fieldObj->flags & \ProcessWire\Field::flagSystem),
                'required' => (bool) $fieldObj->required,
                'templates' => $templates,
                'template_count' => count($templates),
            ];

            // Add type-specific configuration
            $settings = [];

            switch ($fieldObj->type->className()) {
                case 'FieldtypePage':
                    $settings = [
                        'derefAsPage' => $fieldObj->derefAsPage,
                        'template_id' => $fieldObj->template_id,
                        'parent_id' => $fieldObj->parent_id,
                        'inputfield' => $fieldObj->inputfield,
                    ];
                    break;

                case 'FieldtypeText':
                case 'FieldtypeTextarea':
                    $settings = [
                        'maxlength' => $fieldObj->maxlength ?? null,
                        'contentType' => $fieldObj->contentType ?? 0,
                        'stripTags' => $fieldObj->stripTags ?? false,
                    ];
                    break;

                case 'FieldtypeInteger':
                case 'FieldtypeFloat':
                    $settings = [
                        'min' => $fieldObj->min ?? null,
                        'max' => $fieldObj->max ?? null,
                        'inputType' => $fieldObj->inputType ?? 'text',
                    ];
                    break;

                case 'FieldtypeFile':
                case 'FieldtypeImage':
                    $settings = [
                        'maxFiles' => $fieldObj->maxFiles ?? 0,
                        'extensions' => $fieldObj->extensions ?? '',
                        'descriptionRows' => $fieldObj->descriptionRows ?? 0,
                    ];
                    if ($fieldObj->type->className() === 'FieldtypeImage') {
                        $settings['maxWidth'] = $fieldObj->maxWidth ?? 0;
                        $settings['maxHeight'] = $fieldObj->maxHeight ?? 0;
                    }
                    break;

                case 'FieldtypeOptions':
                    $options = [];
                    if ($fieldObj->type instanceof \ProcessWire\FieldtypeOptions) {
                        $mgr = $fieldObj->type->getOptions($fieldObj);
                        foreach ($mgr as $opt) {
                            $options[] = [
                                'id' => $opt->id,
                                'value' => $opt->value,
                                'title' => $opt->title,
                            ];
                        }
                    }
                    $settings = [
                        'options' => $options,
                        'inputfieldClass' => $fieldObj->inputfieldClass ?? '',
                    ];
                    break;

                case 'FieldtypeRepeater':
                case 'FieldtypeRepeaterMatrix':
                    $repeaterFields = [];
                    if ($fieldObj->repeaterFields) {
                        foreach ($fieldObj->repeaterFields as $rfId) {
                            $rf = $this->fields()->get($rfId);
                            if ($rf) {
                                $repeaterFields[] = $rf->name;
                            }
                        }
                    }
                    $settings = [
                        'repeaterFields' => $repeaterFields,
                    ];
                    break;
            }

            if (!empty($settings)) {
                $result['settings'] = $settings;
            }

            return $this->success($result);

        } catch (\Throwable $e) {
            return $this->error('Failed to get field: ' . $e->getMessage());
        }
    }

    /**
     * Get all field types available in the system
     *
     * @return array List of field types
     */
    #[McpTool(
        name: 'list_field_types',
        description: 'List all available field types in the system.'
    )]
    public function listFieldTypes(): array
    {
        try {
            $fieldtypes = $this->wire->wire('fieldtypes');
            $results = [];

            foreach ($fieldtypes as $fieldtype) {
                $className = $fieldtype->className();

                // Count fields using this type
                $fieldCount = 0;
                foreach ($this->fields() as $field) {
                    if ($field->type->className() === $className) {
                        $fieldCount++;
                    }
                }

                $results[] = [
                    'name' => $className,
                    'short_name' => str_replace('Fieldtype', '', $className),
                    'field_count' => $fieldCount,
                ];
            }

            // Sort by name
            usort($results, fn($a, $b) => strcasecmp($a['name'], $b['name']));

            return $this->success([
                'count' => count($results),
                'types' => $results,
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to list field types: ' . $e->getMessage());
        }
    }

    /**
     * Create a new field
     *
     * @param string $name Field name
     * @param string $type Field type (e.g., "text", "textarea", "page")
     * @param string|null $label Field label
     * @param string|null $description Field description
     * @param string|null $tags Field tags
     * @param array $settings Additional field settings
     * @return array Created field info
     */
    #[McpTool(
        name: 'create_field',
        description: 'Create a new field. Specify name and type (e.g., "text", "textarea", "page"). Optionally set label, description, tags, and additional settings.'
    )]
    public function createField(
        string $name,
        string $type,
        ?string $label = null,
        ?string $description = null,
        ?string $tags = null,
        #[Schema(type: 'object', description: 'Additional field settings as key => value pairs', additionalProperties: true)]
        array $settings = []
    ): array {
        try {
            $sanitized = $this->sanitizer()->fieldName($name);
            if (empty($sanitized)) {
                return $this->error("Invalid field name: {$name}");
            }

            if ($this->fields()->get($sanitized)) {
                return $this->error("Field already exists: {$sanitized}");
            }

            // Normalize type name: prepend "Fieldtype" if not already present
            if (stripos($type, 'Fieldtype') !== 0) {
                $fullType = 'Fieldtype' . ucfirst($type);
            } else {
                $fullType = $type;
            }

            $fieldtypeObj = $this->wire->wire('fieldtypes')->get($fullType);
            if (!$fieldtypeObj) {
                $available = [];
                foreach ($this->wire->wire('fieldtypes') as $ft) {
                    $available[] = str_replace('Fieldtype', '', $ft->className());
                }
                return $this->error(
                    "Unknown field type: {$fullType}. Available types: " . implode(', ', $available)
                );
            }

            $field = new \ProcessWire\Field();
            $field->type = $fieldtypeObj;
            $field->name = $sanitized;

            if ($label !== null) {
                $field->label = $label;
            }
            if ($description !== null) {
                $field->description = $description;
            }
            if ($tags !== null) {
                $field->tags = $tags;
            }

            foreach ($settings as $key => $value) {
                $field->set($key, $value);
            }

            $this->fields()->save($field);

            return $this->success([
                'id' => $field->id,
                'name' => $field->name,
                'type' => $field->type->className(),
                'label' => $field->label ?: $field->name,
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to create field: ' . $e->getMessage());
        }
    }

    /**
     * Update field properties
     *
     * @param string $field Field name or ID
     * @param string|null $label New label
     * @param string|null $description New description
     * @param bool|null $required Whether the field is required
     * @param string|null $tags Field tags
     * @param string|null $icon Field icon
     * @param int|null $columnWidth Column width percentage
     * @param int|null $collapsed Collapsed state
     * @param string|null $showIf Show-if selector
     * @param string|null $requiredIf Required-if selector
     * @param string|null $notes Field notes
     * @param array $settings Additional field settings
     * @return array Updated field info
     */
    #[McpTool(
        name: 'update_field',
        description: 'Update field properties. Can set label, description, required, tags, icon, columnWidth, collapsed, showIf, requiredIf, notes, and additional settings.'
    )]
    public function updateField(
        string $field,
        ?string $label = null,
        ?string $description = null,
        ?bool $required = null,
        ?string $tags = null,
        ?string $icon = null,
        ?int $columnWidth = null,
        ?int $collapsed = null,
        ?string $showIf = null,
        ?string $requiredIf = null,
        ?string $notes = null,
        #[Schema(type: 'object', description: 'Additional field settings as key => value pairs', additionalProperties: true)]
        array $settings = []
    ): array {
        try {
            $fieldObj = $this->fields()->get($field);
            if (!$fieldObj) {
                return $this->error("Field not found: {$field}", 'NOT_FOUND');
            }

            if ($fieldObj->flags & \ProcessWire\Field::flagSystem) {
                return $this->error("Cannot modify system field: {$fieldObj->name}");
            }

            if ($label !== null) {
                $fieldObj->label = $label;
            }
            if ($description !== null) {
                $fieldObj->description = $description;
            }
            if ($required !== null) {
                $fieldObj->required = $required;
            }
            if ($tags !== null) {
                $fieldObj->tags = $tags;
            }
            if ($icon !== null) {
                $fieldObj->icon = $icon;
            }
            if ($columnWidth !== null) {
                $fieldObj->columnWidth = $columnWidth;
            }
            if ($collapsed !== null) {
                $fieldObj->collapsed = $collapsed;
            }
            if ($showIf !== null) {
                $fieldObj->showIf = $showIf;
            }
            if ($requiredIf !== null) {
                $fieldObj->requiredIf = $requiredIf;
            }
            if ($notes !== null) {
                $fieldObj->notes = $notes;
            }

            foreach ($settings as $key => $value) {
                $fieldObj->set($key, $value);
            }

            $this->fields()->save($fieldObj);

            return $this->success([
                'id' => $fieldObj->id,
                'name' => $fieldObj->name,
                'type' => $fieldObj->type->className(),
                'label' => $fieldObj->label ?: $fieldObj->name,
                'description' => $fieldObj->description,
                'required' => (bool) $fieldObj->required,
                'tags' => $fieldObj->tags,
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to update field: ' . $e->getMessage());
        }
    }

    /**
     * Delete a field
     *
     * @param string $field Field name or ID
     * @return array Deleted field info
     */
    #[McpTool(
        name: 'delete_field',
        description: 'Delete a field. The field must not be used by any templates and must not be a system field.'
    )]
    public function deleteField(string $field): array
    {
        try {
            $fieldObj = $this->fields()->get($field);
            if (!$fieldObj) {
                return $this->error("Field not found: {$field}", 'NOT_FOUND');
            }

            if ($fieldObj->flags & \ProcessWire\Field::flagSystem) {
                return $this->error("Cannot delete system field: {$fieldObj->name}");
            }

            // Check if any templates use this field
            $usingTemplates = [];
            foreach ($this->templates() as $template) {
                if ($template->fieldgroup->hasField($fieldObj)) {
                    $usingTemplates[] = $template->name;
                }
            }

            if (!empty($usingTemplates)) {
                return $this->error(
                    "Cannot delete field '{$fieldObj->name}' because it is used by templates: "
                    . implode(', ', $usingTemplates)
                );
            }

            $deletedInfo = [
                'id' => $fieldObj->id,
                'name' => $fieldObj->name,
            ];

            $this->fields()->delete($fieldObj);

            return $this->success($deletedInfo);

        } catch (\Throwable $e) {
            return $this->error('Failed to delete field: ' . $e->getMessage());
        }
    }

    /**
     * Clone an existing field
     *
     * @param string $field Source field name or ID
     * @param string $newName Name for the cloned field
     * @return array Cloned field info
     */
    #[McpTool(
        name: 'clone_field',
        description: 'Clone an existing field with a new name. Copies all settings from the source field.'
    )]
    public function cloneField(string $field, string $newName): array
    {
        try {
            $fieldObj = $this->fields()->get($field);
            if (!$fieldObj) {
                return $this->error("Field not found: {$field}", 'NOT_FOUND');
            }

            $sanitizedName = $this->sanitizer()->fieldName($newName);
            if (empty($sanitizedName)) {
                return $this->error("Invalid field name: {$newName}");
            }

            if ($this->fields()->get($sanitizedName)) {
                return $this->error("Field already exists: {$sanitizedName}");
            }

            $clone = $this->fields()->clone($fieldObj, $sanitizedName);
            if (!$clone) {
                return $this->error("Failed to clone field: {$fieldObj->name}");
            }

            return $this->success([
                'id' => $clone->id,
                'name' => $clone->name,
                'type' => $clone->type->className(),
                'source' => $fieldObj->name,
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to clone field: ' . $e->getMessage());
        }
    }
}
