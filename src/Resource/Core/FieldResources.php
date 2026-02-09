<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Resource\Core;

use Elabx\ProcessWireMcp\Resource\ProcessWireMcpResource;
use Mcp\Capability\Attribute\McpResource;

/**
 * Field resources for ProcessWire MCP
 *
 * Provides read-only access to field data.
 */
class FieldResources extends ProcessWireMcpResource
{
    public static function getResourceInfo(): array
    {
        return [
            'name' => 'field_resources',
            'description' => 'Field data resources for ProcessWire',
            'priority' => 20,
        ];
    }

    /**
     * Get all fields with their configurations
     *
     * @return string JSON-encoded field data
     */
    #[McpResource(
        uri: 'fields://list',
        name: 'field_list',
        description: 'All fields with their types and configurations'
    )]
    public function getFieldList(): string
    {
        $fields = [];

        foreach ($this->fields() as $field) {
            // Count templates using this field
            $templateNames = [];
            foreach ($this->templates() as $template) {
                if ($template->fieldgroup->hasField($field)) {
                    $templateNames[] = $template->name;
                }
            }

            $fieldData = [
                'id' => $field->id,
                'name' => $field->name,
                'type' => $field->type->className(),
                'label' => $field->label ?: $field->name,
                'description' => $field->description,
                'required' => (bool) $field->required,
                'is_system' => (bool) ($field->flags & \ProcessWire\Field::flagSystem),
                'templates' => $templateNames,
            ];

            $fields[] = $fieldData;
        }

        // Sort by name
        usort($fields, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return json_encode([
            'count' => count($fields),
            'fields' => $fields,
        ], JSON_PRETTY_PRINT);
    }
}
