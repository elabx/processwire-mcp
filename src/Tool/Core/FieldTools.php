<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tool\Core;

use Elabx\ProcessWireMcp\Tool\ProcessWireMcpTool;
use Mcp\Capability\Attribute\McpTool;

/**
 * Field management tools for ProcessWire MCP
 *
 * Provides tools for listing and inspecting fields.
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
}
