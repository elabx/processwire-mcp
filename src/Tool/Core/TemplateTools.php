<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tool\Core;

use Elabx\ProcessWireMcp\Tool\ProcessWireMcpTool;
use Mcp\Capability\Attribute\McpTool;

/**
 * Template management tools for ProcessWire MCP
 *
 * Provides tools for listing and inspecting templates.
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
}
