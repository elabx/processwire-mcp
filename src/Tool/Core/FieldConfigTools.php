<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tool\Core;

use Elabx\ProcessWireMcp\Tool\ProcessWireMcpTool;
use Mcp\Capability\Attribute\McpTool;

/**
 * Field configuration and introspection tools for ProcessWire MCP
 *
 * Provides tools for inspecting field options and context-specific
 * field configuration (template context, matrix type context).
 */
class FieldConfigTools extends ProcessWireMcpTool
{
    public static function getToolInfo(): array
    {
        return [
            'name' => 'field_config_tools',
            'description' => 'Field configuration introspection tools for ProcessWire',
            'priority' => 35,
        ];
    }

    /**
     * Get options for a FieldtypeOptions field
     *
     * @param string $fieldName Name of the field
     * @return array Options list with id, value, and title
     */
    #[McpTool(
        name: 'get_field_options',
        description: 'Get all selectable options for a FieldtypeOptions field. Returns each option\'s id, value, and title.'
    )]
    public function getFieldOptions(string $fieldName): array
    {
        try {
            $field = $this->fields()->get($fieldName);

            if (!$field) {
                return $this->error("Field not found: {$fieldName}", 'NOT_FOUND');
            }

            if (!($field->type instanceof \ProcessWire\FieldtypeOptions)) {
                return $this->error(
                    "Field '{$fieldName}' is {$field->type->className()}, not FieldtypeOptions.",
                    'INVALID_INPUT'
                );
            }

            $options = [];
            $mgr = $field->type->getOptions($field);
            foreach ($mgr as $opt) {
                $options[] = [
                    'id' => $opt->id,
                    'value' => $opt->value,
                    'title' => $opt->title,
                ];
            }

            return $this->success([
                'field' => $fieldName,
                'count' => count($options),
                'options' => $options,
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to get field options: ' . $e->getMessage());
        }
    }

    /**
     * Get extended field configuration in a specific context
     *
     * @param string $fieldName Name of the field
     * @param string|null $templateName Template context (for template-specific overrides)
     * @param string|null $matrixFieldName RepeaterMatrix field name (for matrix context)
     * @param string|null $matrixTypeName Matrix type name (required with matrixFieldName)
     * @return array Field configuration data
     */
    #[McpTool(
        name: 'get_field_config',
        description: 'Get extended field configuration including showIf, requiredIf, visibility, and template/matrix context overrides. For matrix context, provide matrixFieldName and matrixTypeName.'
    )]
    public function getFieldConfig(
        string $fieldName,
        ?string $templateName = null,
        ?string $matrixFieldName = null,
        ?string $matrixTypeName = null
    ): array {
        try {
            $field = $this->fields()->get($fieldName);

            if (!$field) {
                return $this->error("Field not found: {$fieldName}", 'NOT_FOUND');
            }

            // Determine the context-specific field object
            $contextField = $field;
            $context = 'global';

            if ($matrixFieldName && $matrixTypeName) {
                // Matrix context: get field config within a specific matrix type
                $matrixField = $this->fields()->get($matrixFieldName);
                if (!$matrixField) {
                    return $this->error("Matrix field not found: {$matrixFieldName}", 'NOT_FOUND');
                }

                if ($matrixField->type->className() !== 'FieldtypeRepeaterMatrix') {
                    return $this->error(
                        "Field '{$matrixFieldName}' is not a RepeaterMatrix field.",
                        'INVALID_INPUT'
                    );
                }

                // Find the matrix type number from name
                $typesInfo = $matrixField->type->getMatrixTypesInfo($matrixField);
                $matrixN = null;
                foreach ($typesInfo as $typeName => $info) {
                    if ($typeName === $matrixTypeName || ($info['name'] ?? '') === $matrixTypeName) {
                        $matrixN = $info['type'] ?? null;
                        break;
                    }
                }

                if ($matrixN === null) {
                    return $this->error(
                        "Matrix type '{$matrixTypeName}' not found in field '{$matrixFieldName}'.",
                        'NOT_FOUND'
                    );
                }

                // Get the repeater template's fieldgroup for context
                $repeaterTemplate = $this->templates()->get("repeater_{$matrixFieldName}");
                if ($repeaterTemplate) {
                    $contextField = $repeaterTemplate->fieldgroup->getFieldContext($field->id, "matrix{$matrixN}");
                    if (!$contextField) {
                        // Fall back to base repeater context
                        $contextField = $repeaterTemplate->fieldgroup->getFieldContext($field);
                        if (!$contextField) {
                            $contextField = $field;
                        }
                    }
                }

                $context = "matrix:{$matrixFieldName}.{$matrixTypeName}";

            } elseif ($templateName) {
                // Template context
                $template = $this->templates()->get($templateName);
                if (!$template) {
                    return $this->error("Template not found: {$templateName}", 'NOT_FOUND');
                }

                if (!$template->fieldgroup->hasField($field)) {
                    return $this->error(
                        "Field '{$fieldName}' is not in template '{$templateName}'.",
                        'NOT_FOUND'
                    );
                }

                $contextField = $template->fieldgroup->getFieldContext($field);
                if (!$contextField) {
                    $contextField = $field;
                }

                $context = "template:{$templateName}";
            }

            // Build the config response
            $config = [
                'field' => $fieldName,
                'context' => $context,
                'label' => $contextField->label ?: $field->label ?: $field->name,
                'description' => $contextField->description ?: $field->description,
                'type' => $field->type->className(),
                'required' => (bool) ($contextField->required ?? $field->required),
                'requiredIf' => $contextField->requiredIf ?? $field->requiredIf ?? '',
                'showIf' => $contextField->showIf ?? $field->showIf ?? '',
                'columnWidth' => (int) ($contextField->columnWidth ?? $field->columnWidth ?? 100),
                'collapsed' => (int) ($contextField->collapsed ?? $field->collapsed ?? 0),
                'visibility' => (int) ($contextField->visibility ?? $field->visibility ?? 0),
            ];

            // Add FieldtypePage-specific config
            if ($field->type->className() === 'FieldtypePage') {
                $config['template_id'] = $contextField->template_id ?? $field->template_id ?? 0;
                $config['template_ids'] = $contextField->template_ids ?? $field->template_ids ?? [];
                $config['parent_id'] = $contextField->parent_id ?? $field->parent_id ?? 0;
                $config['findPagesSelector'] = $contextField->findPagesSelector ?? $field->findPagesSelector ?? '';
                $config['inputfield'] = $contextField->inputfield ?? $field->inputfield ?? '';
            }

            return $this->success($config);

        } catch (\Throwable $e) {
            return $this->error('Failed to get field config: ' . $e->getMessage());
        }
    }
}
