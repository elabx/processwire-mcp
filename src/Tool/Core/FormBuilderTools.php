<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tool\Core;

use Elabx\ProcessWireMcp\Tool\ProcessWireMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

/**
 * FormBuilder management tools for ProcessWire MCP
 *
 * Provides tools for managing FormBuilder forms: listing, reading,
 * creating, and deleting forms.
 */
class FormBuilderTools extends ProcessWireMcpTool
{
    public static function getToolInfo(): array
    {
        return [
            'name' => 'form_builder_tools',
            'description' => 'FormBuilder form management tools for ProcessWire',
            'priority' => 65,
        ];
    }

    /**
     * Get the FormBuilder module instance
     */
    private function forms(): mixed
    {
        $modules = $this->modules();
        if (!$modules->isInstalled('FormBuilder')) {
            return null;
        }
        return $this->get('forms');
    }

    /**
     * List all FormBuilder forms
     */
    #[McpTool(
        name: 'list_forms',
        description: 'List all FormBuilder forms with their names, IDs, and basic info.'
    )]
    public function listForms(): array
    {
        try {
            $forms = $this->forms();
            if (!$forms) {
                return $this->error('FormBuilder module is not installed.', 'NOT_AVAILABLE');
            }

            $results = [];
            foreach ($forms as $formName) {
                $form = $forms->load($formName);
                if ($form) {
                    $results[] = [
                        'id' => $form->id,
                        'name' => $form->name,
                        'framework' => $form->framework ?? '',
                        'submitText' => $form->submitText ?? '',
                        'created' => $form->created ?? 0,
                        'modified' => $form->modified ?? 0,
                    ];
                }
            }

            return $this->success([
                'count' => count($results),
                'forms' => $results,
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to list forms: ' . $e->getMessage());
        }
    }

    /**
     * Get form details by name
     */
    #[McpTool(
        name: 'get_form',
        description: 'Get detailed information about a FormBuilder form by name, including all its fields.'
    )]
    public function getForm(string $formName): array
    {
        try {
            $forms = $this->forms();
            if (!$forms) {
                return $this->error('FormBuilder module is not installed.', 'NOT_AVAILABLE');
            }

            $form = $forms->load($formName);
            if (!$form) {
                return $this->error("Form not found: {$formName}", 'NOT_FOUND');
            }

            $fields = [];
            foreach ($form->children() as $field) {
                $fieldData = [
                    'name' => $field->name,
                    'type' => $field->type,
                    'label' => $field->label ?? '',
                    'required' => $field->required ?? false,
                ];
                if ($field->placeholder) {
                    $fieldData['placeholder'] = $field->placeholder;
                }
                if ($field->description) {
                    $fieldData['description'] = $field->description;
                }
                $fields[] = $fieldData;
            }

            return $this->success([
                'id' => $form->id,
                'name' => $form->name,
                'framework' => $form->framework ?? '',
                'submitText' => $form->submitText ?? '',
                'action' => $form->action ?? '',
                'method' => $form->method ?? 'post',
                'created' => $form->created ?? 0,
                'modified' => $form->modified ?? 0,
                'fields' => $fields,
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to get form: ' . $e->getMessage());
        }
    }

    /**
     * Create a new FormBuilder form with fields
     */
    #[McpTool(
        name: 'create_form',
        description: 'Create a new FormBuilder form with fields. Each field needs at minimum a name and type.'
    )]
    public function createForm(
        string $formName,
        string $framework = 'Uikit3',
        string $submitText = 'Submit',
        #[Schema(
            type: 'array',
            description: 'Array of field definitions. Each field needs: name (string), type (string: Text, Email, Checkbox, Textarea, Select, Hidden, etc.), and optionally: label, required (bool), placeholder, description, options (for Select/Radios).',
            items: ['type' => 'object', 'additionalProperties' => true]
        )]
        array $fields = []
    ): array {
        try {
            $forms = $this->forms();
            if (!$forms) {
                return $this->error('FormBuilder module is not installed.', 'NOT_AVAILABLE');
            }

            // Check if form already exists
            $existing = $forms->load($formName);
            if ($existing) {
                return $this->error("Form already exists: {$formName}", 'ALREADY_EXISTS');
            }

            $form = $forms->addNew($formName);
            $form->framework = $framework;
            $form->submitText = $submitText;

            $addedFields = [];
            foreach ($fields as $fieldDef) {
                if (empty($fieldDef['name']) || empty($fieldDef['type'])) {
                    continue;
                }

                $f = new \ProcessWire\FormBuilderField();
                $f->name = $fieldDef['name'];
                $f->type = $fieldDef['type'];

                if (!empty($fieldDef['label'])) {
                    $f->label = $fieldDef['label'];
                }
                if (!empty($fieldDef['required'])) {
                    $f->required = true;
                }
                if (!empty($fieldDef['placeholder'])) {
                    $f->set('placeholder', $fieldDef['placeholder']);
                }
                if (!empty($fieldDef['description'])) {
                    $f->description = $fieldDef['description'];
                }
                if (!empty($fieldDef['columnWidth'])) {
                    $f->columnWidth = (int) $fieldDef['columnWidth'];
                }
                if (!empty($fieldDef['options'])) {
                    $f->set('options', $fieldDef['options']);
                }

                $form->add($f);
                $addedFields[] = $fieldDef['name'];
            }

            $form->save();

            return $this->success([
                'id' => $form->id,
                'name' => $form->name,
                'framework' => $form->framework,
                'fields_added' => $addedFields,
            ], "Form '{$formName}' created successfully");

        } catch (\Throwable $e) {
            return $this->error('Failed to create form: ' . $e->getMessage());
        }
    }

    /**
     * Delete a FormBuilder form
     */
    #[McpTool(
        name: 'delete_form',
        description: 'Delete a FormBuilder form by name. Requires confirm=true to proceed.'
    )]
    public function deleteForm(string $formName, bool $confirm = false): array
    {
        try {
            if (!$confirm) {
                return $this->error(
                    "Deletion requires confirm=true. This will permanently delete form '{$formName}' and all its entries.",
                    'CONFIRMATION_REQUIRED'
                );
            }

            $forms = $this->forms();
            if (!$forms) {
                return $this->error('FormBuilder module is not installed.', 'NOT_AVAILABLE');
            }

            $form = $forms->load($formName);
            if (!$form) {
                return $this->error("Form not found: {$formName}", 'NOT_FOUND');
            }

            $formId = $form->id;
            $forms->delete($form);

            return $this->success([
                'id' => $formId,
                'name' => $formName,
            ], "Form '{$formName}' deleted successfully");

        } catch (\Throwable $e) {
            return $this->error('Failed to delete form: ' . $e->getMessage());
        }
    }
}
