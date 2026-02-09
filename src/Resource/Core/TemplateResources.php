<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Resource\Core;

use Elabx\ProcessWireMcp\Resource\ProcessWireMcpResource;
use Mcp\Capability\Attribute\McpResource;

/**
 * Template resources for ProcessWire MCP
 *
 * Provides read-only access to template data.
 */
class TemplateResources extends ProcessWireMcpResource
{
    public static function getResourceInfo(): array
    {
        return [
            'name' => 'template_resources',
            'description' => 'Template data resources for ProcessWire',
            'priority' => 10,
        ];
    }

    /**
     * Get all templates with their fields
     *
     * @return string JSON-encoded template data
     */
    #[McpResource(
        uri: 'templates://list',
        name: 'template_list',
        description: 'All templates with their fields and configurations'
    )]
    public function getTemplateList(): string
    {
        $templates = [];

        foreach ($this->templates() as $template) {
            // Get fields for this template
            $fields = [];
            foreach ($template->fieldgroup as $field) {
                $fields[] = [
                    'name' => $field->name,
                    'type' => $field->type->className(),
                    'label' => $field->label ?: $field->name,
                    'required' => (bool) $field->required,
                ];
            }

            $templates[] = [
                'id' => $template->id,
                'name' => $template->name,
                'label' => $template->label ?: $template->name,
                'is_system' => (bool) ($template->flags & \ProcessWire\Template::flagSystem),
                'fields' => $fields,
            ];
        }

        return json_encode([
            'count' => count($templates),
            'templates' => $templates,
        ], JSON_PRETTY_PRINT);
    }
}
