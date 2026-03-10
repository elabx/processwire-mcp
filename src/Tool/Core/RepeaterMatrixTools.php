<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tool\Core;

use Elabx\ProcessWireMcp\Tool\ProcessWireMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use ProcessWire\NullPage;

/**
 * RepeaterMatrix CRUD tools for ProcessWire MCP
 *
 * Provides tools for managing RepeaterMatrix items: listing types,
 * reading items, adding, updating, and deleting matrix items.
 */
class RepeaterMatrixTools extends ProcessWireMcpTool
{
    public static function getToolInfo(): array
    {
        return [
            'name' => 'repeater_matrix_tools',
            'description' => 'RepeaterMatrix CRUD tools for ProcessWire',
            'priority' => 60,
        ];
    }

    /**
     * List all matrix types for a RepeaterMatrix field
     *
     * @param string $fieldName Name of the RepeaterMatrix field
     * @return array Matrix type definitions with their fields
     */
    #[McpTool(
        name: 'get_matrix_types',
        description: 'List all matrix types for a RepeaterMatrix field, including each type\'s fields, labels, and option values.'
    )]
    public function getMatrixTypes(string $fieldName): array
    {
        try {
            $field = $this->fields()->get($fieldName);

            if (!$field) {
                return $this->error("Field not found: {$fieldName}", 'NOT_FOUND');
            }

            if ($field->type->className() !== 'FieldtypeRepeaterMatrix') {
                return $this->error(
                    "Field '{$fieldName}' is {$field->type->className()}, not FieldtypeRepeaterMatrix.",
                    'INVALID_INPUT'
                );
            }

            // Use the native getMatrixTypesInfo() API
            // Works on both Field (RepeaterMatrixField) and Fieldtype objects
            $matrixInfo = $field->type->getMatrixTypesInfo($field, ['index' => 'type']);

            $types = [];
            foreach ($matrixInfo as $typeName => $info) {
                $typeData = [
                    'n' => $info['n'] ?? 0,
                    'name' => $info['name'],
                    'label' => $info['label'] ?? '',
                    'sort' => $info['sort'] ?? 0,
                    'head' => $info['head'] ?? '',
                    'fields' => [],
                ];

                // Resolve fields from the info array
                $fields = $info['fields'] ?? [];
                foreach ($fields as $fName => $f) {
                    $fieldData = [
                        'name' => $f->name,
                        'label' => $f->label ?: $f->name,
                        'type' => $f->type->className(),
                    ];

                    // Include options for FieldtypeOptions fields
                    if ($f->type instanceof \ProcessWire\FieldtypeOptions) {
                        $options = [];
                        $mgr = $f->type->getOptions($f);
                        foreach ($mgr as $opt) {
                            $options[] = [
                                'id' => $opt->id,
                                'value' => $opt->value,
                                'title' => $opt->title,
                            ];
                        }
                        $fieldData['options'] = $options;
                    }

                    $typeData['fields'][] = $fieldData;
                }

                $types[] = $typeData;
            }

            return $this->success([
                'field' => $fieldName,
                'type_count' => count($types),
                'types' => $types,
            ]);

        } catch (\Throwable $e) {
            return $this->error('Failed to get matrix types: ' . $e->getMessage());
        }
    }

    /**
     * Read all matrix items on a page
     *
     * @param int|string $page Page ID or path
     * @param string $fieldName RepeaterMatrix field name
     * @param string|null $matrixType Optional filter by matrix type name
     * @return array Matrix items with field values
     */
    #[McpTool(
        name: 'get_matrix_items',
        description: 'Read all RepeaterMatrix items on a page with their field values. Optionally filter by matrix type name.'
    )]
    public function getMatrixItems(
        int|string $page,
        string $fieldName,
        ?string $matrixType = null
    ): array {
        try {
            $pageObj = $this->pages()->get($page);

            if (!$pageObj || $pageObj instanceof NullPage || !$pageObj->id) {
                return $this->error("Page not found: {$page}", 'NOT_FOUND');
            }

            $this->assertNotAdminPage($pageObj);

            $field = $this->fields()->get($fieldName);
            if (!$field) {
                return $this->error("Field not found: {$fieldName}", 'NOT_FOUND');
            }

            if ($field->type->className() !== 'FieldtypeRepeaterMatrix') {
                return $this->error(
                    "Field '{$fieldName}' is not a RepeaterMatrix field.",
                    'INVALID_INPUT'
                );
            }

            $items = $pageObj->getUnformatted($fieldName);
            $results = [];

            foreach ($items as $item) {
                $typeName = $item->matrix('name');

                // Filter by matrix type if specified
                if ($matrixType !== null && $typeName !== $matrixType) {
                    continue;
                }

                $itemData = [
                    'id' => $item->id,
                    'sort' => $item->sort,
                    'status' => $item->status,
                    'matrix_type' => $typeName,
                    'matrix_label' => $item->matrix('label'),
                    'fields' => [],
                ];

                // Get field values for this item's matrix type
                foreach ($item->getFields() as $f) {
                    $itemData['fields'][$f->name] = $this->getFieldValue($item, $f->name);
                }

                $results[] = $itemData;
            }

            return $this->success([
                'page_id' => $pageObj->id,
                'page_path' => $pageObj->path,
                'field' => $fieldName,
                'count' => count($results),
                'items' => $results,
            ]);

        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'ACCESS_DENIED');
        } catch (\Throwable $e) {
            return $this->error('Failed to get matrix items: ' . $e->getMessage());
        }
    }

    /**
     * Add a new matrix item to a page
     *
     * @param int|string $page Page ID or path
     * @param string $fieldName RepeaterMatrix field name
     * @param string $matrixType Matrix type name
     * @param array $fieldValues Associative array of field values
     * @return array Created item data
     */
    #[McpTool(
        name: 'add_matrix_item',
        description: 'Add a new RepeaterMatrix item to a page. Specify the matrix type name and field values.'
    )]
    public function addMatrixItem(
        int|string $page,
        string $fieldName,
        string $matrixType,
        #[Schema(type: 'object', description: 'Object of field name => value pairs for the new item', additionalProperties: true)]
        array $fieldValues = []
    ): array {
        try {
            $pageObj = $this->pages()->get($page);

            if (!$pageObj || $pageObj instanceof NullPage || !$pageObj->id) {
                return $this->error("Page not found: {$page}", 'NOT_FOUND');
            }

            $this->assertNotAdminPage($pageObj);

            $field = $this->fields()->get($fieldName);
            if (!$field) {
                return $this->error("Field not found: {$fieldName}", 'NOT_FOUND');
            }

            if ($field->type->className() !== 'FieldtypeRepeaterMatrix') {
                return $this->error(
                    "Field '{$fieldName}' is not a RepeaterMatrix field.",
                    'INVALID_INPUT'
                );
            }

            $items = $pageObj->getUnformatted($fieldName);

            if (!$items) {
                // Trigger field initialization by saving the page with the field
                $pageObj->save($fieldName);
                $items = $pageObj->getUnformatted($fieldName);
            }

            $newItem = $items ? $items->getNewItem() : null;

            if (!$newItem) {
                return $this->error(
                    "Could not create new matrix item. The repeater field structure may not be initialized. "
                    . "Try saving the page in the admin first, or ensure the field is properly added to the template.",
                    'INTERNAL_ERROR'
                );
            }

            $newItem->setMatrixType($matrixType);

            // Set field values — use set() directly without hasField() check,
            // as matrix type field mappings may not be in PW's runtime cache
            $set = [];

            foreach ($fieldValues as $fName => $fValue) {
                $newItem->set($fName, $fValue);
                $set[] = $fName;
            }

            $newItem->save();
            $pageObj->save($fieldName);

            $result = [
                'id' => $newItem->id,
                'matrix_type' => $newItem->matrix('name'),
                'set_fields' => $set,
            ];

            return $this->success($result, "Matrix item added with ID {$newItem->id}");

        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'ACCESS_DENIED');
        } catch (\Throwable $e) {
            return $this->error('Failed to add matrix item: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing matrix item by its page ID
     *
     * @param int $itemId The repeater item page ID
     * @param array $fieldValues Associative array of field values to update
     * @return array Updated item data
     */
    #[McpTool(
        name: 'update_matrix_item',
        description: 'Update a RepeaterMatrix item by its page ID. Provide field => value pairs to update.'
    )]
    public function updateMatrixItem(
        int $itemId,
        #[Schema(type: 'object', description: 'Object of field name => value pairs to update', additionalProperties: true)]
        array $fieldValues
    ): array {
        try {
            $item = $this->pages()->get($itemId);

            if (!$item || $item instanceof NullPage || !$item->id) {
                return $this->error("Item not found: {$itemId}", 'NOT_FOUND');
            }

            // Validate it's a repeater page
            if (!$this->isRepeaterPage($item)) {
                return $this->error(
                    "Page {$itemId} is not a repeater item.",
                    'INVALID_INPUT'
                );
            }

            // Security: check the content page that owns this repeater
            $this->assertContentPageNotAdmin($item);

            if (empty($fieldValues)) {
                return $this->error('No field values provided to update.', 'INVALID_INPUT');
            }

            $updated = [];
            $skipped = [];

            foreach ($fieldValues as $fName => $fValue) {
                $item->set($fName, $fValue);
                $updated[] = $fName;
            }

            $item->save();

            $result = [
                'id' => $item->id,
                'matrix_type' => $item->matrix('name'),
                'updated_fields' => $updated,
            ];

            return $this->success($result, 'Matrix item updated successfully');

        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'ACCESS_DENIED');
        } catch (\Throwable $e) {
            return $this->error('Failed to update matrix item: ' . $e->getMessage());
        }
    }

    /**
     * Create a new matrix type on a RepeaterMatrix field
     *
     * @param string $fieldName Name of the RepeaterMatrix field
     * @param string $name Machine name for the matrix type (lowercase, no spaces)
     * @param string $label Human-readable label
     * @param array $fields Array of field names to include in this matrix type
     * @param string|null $head Head format string (e.g. "{matrix_label}: {headline}")
     * @return array Created matrix type info
     */
    #[McpTool(
        name: 'create_matrix_type',
        description: 'Create a new matrix type on a RepeaterMatrix field. Specify the type name, label, and which fields to include.'
    )]
    public function createMatrixType(
        string $fieldName,
        string $name,
        string $label,
        #[Schema(type: 'array', description: 'Array of field names to include in this matrix type')]
        array $fields,
        ?string $head = null
    ): array {
        try {
            $field = $this->fields()->get($fieldName);

            if (!$field) {
                return $this->error("Field not found: {$fieldName}", 'NOT_FOUND');
            }

            if ($field->type->className() !== 'FieldtypeRepeaterMatrix') {
                return $this->error(
                    "Field '{$fieldName}' is {$field->type->className()}, not FieldtypeRepeaterMatrix.",
                    'INVALID_INPUT'
                );
            }

            // Sanitize the type name
            $name = $this->sanitizer()->fieldName($name);
            if (!$name) {
                return $this->error('Invalid matrix type name.', 'INVALID_INPUT');
            }

            // Check for duplicate names
            $data = $field->getArray();
            for ($i = 0; $i <= 100; $i++) {
                if (isset($data["matrix{$i}_name"]) && $data["matrix{$i}_name"] === $name) {
                    return $this->error("Matrix type '{$name}' already exists (n={$i}).", 'ALREADY_EXISTS');
                }
            }

            // Find the next available type number
            $n = 0;
            for ($i = 1; $i <= 100; $i++) {
                if (empty($data["matrix{$i}_name"])) {
                    $n = $i;
                    break;
                }
            }

            if ($n === 0) {
                return $this->error('No available matrix type slot (max 100 types).', 'LIMIT_REACHED');
            }

            // Resolve field names to IDs and validate they exist
            $fieldIds = [];
            $resolvedFields = [];
            $notFound = [];

            foreach ($fields as $fName) {
                $f = $this->fields()->get($fName);
                if ($f) {
                    $fieldIds[] = $f->id;
                    $resolvedFields[] = $f->name;
                } else {
                    $notFound[] = $fName;
                }
            }

            if (!empty($notFound)) {
                return $this->error(
                    'Fields not found: ' . implode(', ', $notFound),
                    'NOT_FOUND'
                );
            }

            // Set matrix type properties on the field first
            $field->set("matrix{$n}_name", $name);
            $field->set("matrix{$n}_label", $label);
            $field->set("matrix{$n}_sort", $n);
            $field->set("matrix{$n}_fields", $fieldIds);

            // Ensure fields are in the repeater's fieldgroup
            $repeaterTemplate = $field->type->getMatrixTemplate($field);
            if ($repeaterTemplate) {
                $fieldgroup = $repeaterTemplate->fieldgroup;
                $added = [];
                foreach ($fieldIds as $fId) {
                    $f = $this->fields()->get($fId);
                    if ($f && !$fieldgroup->hasField($f)) {
                        $fieldgroup->add($f);
                        $added[] = $f->name;
                    }
                }
                if (!empty($added)) {
                    $fieldgroup->save();
                }
            }

            if ($head !== null) {
                $field->set("matrix{$n}_head", $head);
            }

            $this->fields()->save($field);

            $result = [
                'n' => $n,
                'name' => $name,
                'label' => $label,
                'fields' => $resolvedFields,
            ];

            if ($head !== null) {
                $result['head'] = $head;
            }

            return $this->success($result, "Matrix type '{$name}' created as type {$n}");

        } catch (\Throwable $e) {
            return $this->error('Failed to create matrix type: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing matrix type on a RepeaterMatrix field
     *
     * @param string $fieldName Name of the RepeaterMatrix field
     * @param string $name Current machine name of the matrix type to update
     * @param string|null $label New label (null to keep current)
     * @param string|null $head New head format string (null to keep current)
     * @param array $addFields Field names to add to this matrix type
     * @param array $removeFields Field names to remove from this matrix type
     * @return array Updated matrix type info
     */
    #[McpTool(
        name: 'update_matrix_type',
        description: 'Update an existing matrix type. Can change label, head format, add fields, and remove fields.'
    )]
    public function updateMatrixType(
        string $fieldName,
        string $name,
        ?string $label = null,
        ?string $head = null,
        #[Schema(type: 'array', description: 'Field names to add to this matrix type')]
        array $addFields = [],
        #[Schema(type: 'array', description: 'Field names to remove from this matrix type')]
        array $removeFields = []
    ): array {
        try {
            $field = $this->fields()->get($fieldName);

            if (!$field) {
                return $this->error("Field not found: {$fieldName}", 'NOT_FOUND');
            }

            if ($field->type->className() !== 'FieldtypeRepeaterMatrix') {
                return $this->error(
                    "Field '{$fieldName}' is {$field->type->className()}, not FieldtypeRepeaterMatrix.",
                    'INVALID_INPUT'
                );
            }

            // Find the type number for this name
            $data = $field->getArray();
            $n = null;
            for ($i = 0; $i <= 100; $i++) {
                if (isset($data["matrix{$i}_name"]) && $data["matrix{$i}_name"] === $name) {
                    $n = $i;
                    break;
                }
            }

            if ($n === null) {
                return $this->error("Matrix type '{$name}' not found on field '{$fieldName}'.", 'NOT_FOUND');
            }

            $changes = [];

            // Update label
            if ($label !== null) {
                $field->set("matrix{$n}_label", $label);
                $changes[] = 'label';
            }

            // Update head
            if ($head !== null) {
                $field->set("matrix{$n}_head", $head);
                $changes[] = 'head';
            }

            // Get current field IDs (value may be array or comma-separated string)
            $rawFields = $data["matrix{$n}_fields"] ?? [];
            if (is_string($rawFields)) {
                $currentFieldIds = array_filter(
                    array_map('intval', explode(',', $rawFields)),
                    fn($id) => $id > 0
                );
            } else {
                $currentFieldIds = array_filter(
                    array_map('intval', (array) $rawFields),
                    fn($id) => $id > 0
                );
            }

            // Add fields
            $addedFields = [];
            $notFound = [];
            foreach ($addFields as $fName) {
                $f = $this->fields()->get($fName);
                if (!$f) {
                    $notFound[] = $fName;
                    continue;
                }
                if (!in_array($f->id, $currentFieldIds)) {
                    $currentFieldIds[] = $f->id;
                    $addedFields[] = $f->name;
                }
            }

            if (!empty($notFound)) {
                return $this->error('Fields not found: ' . implode(', ', $notFound), 'NOT_FOUND');
            }

            // Remove fields
            $removedFields = [];
            foreach ($removeFields as $fName) {
                $f = $this->fields()->get($fName);
                if (!$f) {
                    $notFound[] = $fName;
                    continue;
                }
                $key = array_search($f->id, $currentFieldIds);
                if ($key !== false) {
                    unset($currentFieldIds[$key]);
                    $removedFields[] = $f->name;
                }
            }

            if (!empty($notFound)) {
                return $this->error('Fields not found: ' . implode(', ', $notFound), 'NOT_FOUND');
            }

            // Update field IDs if changed
            if (!empty($addedFields) || !empty($removedFields)) {
                $currentFieldIds = array_values($currentFieldIds);
                $field->set("matrix{$n}_fields", $currentFieldIds);
                if (!empty($addedFields)) $changes[] = 'added_fields';
                if (!empty($removedFields)) $changes[] = 'removed_fields';

                // Ensure added fields are in the repeater's fieldgroup
                if (!empty($addedFields)) {
                    $repeaterTemplate = $field->type->getMatrixTemplate($field);
                    if ($repeaterTemplate) {
                        $fieldgroup = $repeaterTemplate->fieldgroup;
                        foreach ($addedFields as $afName) {
                            $af = $this->fields()->get($afName);
                            if ($af && !$fieldgroup->hasField($af)) {
                                $fieldgroup->add($af);
                            }
                        }
                        $fieldgroup->save();
                    }
                }
            }

            if (empty($changes)) {
                return $this->error('No changes specified.', 'INVALID_INPUT');
            }

            $this->fields()->save($field);

            // Resolve current field names for response
            $currentFieldNames = [];
            foreach ($currentFieldIds as $fId) {
                $f = $this->fields()->get($fId);
                if ($f) $currentFieldNames[] = $f->name;
            }

            $result = [
                'n' => $n,
                'name' => $name,
                'label' => $label ?? ($data["matrix{$n}_label"] ?? ''),
                'changes' => $changes,
                'fields' => $currentFieldNames,
            ];

            if (!empty($addedFields)) $result['added_fields'] = $addedFields;
            if (!empty($removedFields)) $result['removed_fields'] = $removedFields;

            return $this->success($result, "Matrix type '{$name}' updated");

        } catch (\Throwable $e) {
            return $this->error('Failed to update matrix type: ' . $e->getMessage());
        }
    }

    /**
     * Delete a matrix type from a RepeaterMatrix field
     *
     * @param string $fieldName Name of the RepeaterMatrix field
     * @param string $name Machine name of the matrix type to delete
     * @return array Deletion result
     */
    #[McpTool(
        name: 'delete_matrix_type',
        description: 'Delete a matrix type from a RepeaterMatrix field by name. Removes the type definition; does not delete existing items using this type.'
    )]
    public function deleteMatrixType(
        string $fieldName,
        string $name
    ): array {
        try {
            $field = $this->fields()->get($fieldName);

            if (!$field) {
                return $this->error("Field not found: {$fieldName}", 'NOT_FOUND');
            }

            if ($field->type->className() !== 'FieldtypeRepeaterMatrix') {
                return $this->error(
                    "Field '{$fieldName}' is {$field->type->className()}, not FieldtypeRepeaterMatrix.",
                    'INVALID_INPUT'
                );
            }

            // Find the type number for this name
            $data = $field->getArray();
            $n = null;
            for ($i = 0; $i <= 100; $i++) {
                if (isset($data["matrix{$i}_name"]) && $data["matrix{$i}_name"] === $name) {
                    $n = $i;
                    break;
                }
            }

            if ($n === null) {
                return $this->error("Matrix type '{$name}' not found on field '{$fieldName}'.", 'NOT_FOUND');
            }

            // Remove all matrix{N}_* properties
            $field->set("matrix{$n}_name", '');
            $field->set("matrix{$n}_label", '');
            $field->set("matrix{$n}_sort", 0);
            $field->set("matrix{$n}_head", '');
            $field->set("matrix{$n}_fields", '');

            $this->fields()->save($field);

            return $this->success([
                'n' => $n,
                'name' => $name,
                'field' => $fieldName,
            ], "Matrix type '{$name}' (n={$n}) deleted from field '{$fieldName}'");

        } catch (\Throwable $e) {
            return $this->error('Failed to delete matrix type: ' . $e->getMessage());
        }
    }

    /**
     * Delete a matrix item by its page ID
     *
     * @param int $itemId The repeater item page ID
     * @return array Deletion result
     */
    #[McpTool(
        name: 'delete_matrix_item',
        description: 'Delete a RepeaterMatrix item by its page ID.'
    )]
    public function deleteMatrixItem(int $itemId): array
    {
        try {
            $item = $this->pages()->get($itemId);

            if (!$item || $item instanceof NullPage || !$item->id) {
                return $this->error("Item not found: {$itemId}", 'NOT_FOUND');
            }

            // Validate it's a repeater page
            if (!$this->isRepeaterPage($item)) {
                return $this->error(
                    "Page {$itemId} is not a repeater item.",
                    'INVALID_INPUT'
                );
            }

            // Security: check the content page
            $this->assertContentPageNotAdmin($item);

            $contentPage = $item->getForPage();
            $field = $item->getForField();
            $fieldName = $field->name;

            $items = $contentPage->getUnformatted($fieldName);
            $items->remove($item);
            $contentPage->save($fieldName);

            return $this->success([
                'id' => $itemId,
                'content_page_id' => $contentPage->id,
                'field' => $fieldName,
            ], "Matrix item {$itemId} deleted");

        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'ACCESS_DENIED');
        } catch (\Throwable $e) {
            return $this->error('Failed to delete matrix item: ' . $e->getMessage());
        }
    }

    /**
     * Reorder matrix items on a page
     *
     * @param int|string $page Page ID or path
     * @param string $fieldName RepeaterMatrix field name
     * @param array $itemIds Ordered array of item page IDs representing the desired sort order
     * @return array Reorder result
     */
    #[McpTool(
        name: 'sort_matrix_items',
        description: 'Reorder RepeaterMatrix items on a page. Provide an array of item IDs in the desired sort order.'
    )]
    public function sortMatrixItems(
        int|string $page,
        string $fieldName,
        #[Schema(type: 'array', description: 'Array of item page IDs in desired sort order')]
        array $itemIds
    ): array {
        try {
            $pageObj = $this->pages()->get($page);

            if (!$pageObj || $pageObj instanceof NullPage || !$pageObj->id) {
                return $this->error("Page not found: {$page}", 'NOT_FOUND');
            }

            $this->assertNotAdminPage($pageObj);

            $field = $this->fields()->get($fieldName);
            if (!$field) {
                return $this->error("Field not found: {$fieldName}", 'NOT_FOUND');
            }

            if ($field->type->className() !== 'FieldtypeRepeaterMatrix') {
                return $this->error(
                    "Field '{$fieldName}' is not a RepeaterMatrix field.",
                    'INVALID_INPUT'
                );
            }

            $items = $pageObj->getUnformatted($fieldName);

            // Validate all provided IDs belong to this field
            $existingIds = [];
            foreach ($items as $item) {
                $existingIds[] = $item->id;
            }

            $invalidIds = array_diff(array_map('intval', $itemIds), $existingIds);
            if (!empty($invalidIds)) {
                return $this->error(
                    'Item IDs not found in this field: ' . implode(', ', $invalidIds),
                    'NOT_FOUND'
                );
            }

            // Set sort values using wire('pages')->sort()
            $sorted = [];
            foreach ($itemIds as $sortIndex => $id) {
                $id = (int) $id;
                $this->wire()->pages->sort($this->pages()->get($id), $sortIndex);
                $sorted[] = $id;
            }

            // Save the field to persist sort order
            $pageObj->save($fieldName);

            return $this->success([
                'page_id' => $pageObj->id,
                'field' => $fieldName,
                'sorted_ids' => $sorted,
                'count' => count($sorted),
            ], 'Matrix items reordered');

        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'ACCESS_DENIED');
        } catch (\Throwable $e) {
            return $this->error('Failed to sort matrix items: ' . $e->getMessage());
        }
    }

    /**
     * Reorder fields within a RepeaterMatrix type.
     *
     * Controls the order of fields in the admin UI for a specific matrix type.
     * This sets the matrix{N}_fields ID array on the RepeaterMatrix field.
     * Fields listed in fieldOrder are placed first; unlisted fields are appended at the end.
     *
     * @param string $fieldName RepeaterMatrix field name
     * @param string $matrixTypeName Matrix type name
     * @param array $fieldOrder Array of field names in desired order
     * @return array Reorder result with final field order
     */
    #[McpTool(
        name: 'reorder_matrix_type_fields',
        description: <<<'DESC'
Reorder fields within a RepeaterMatrix type definition.
Controls field order in the admin UI for a specific matrix type by rewriting the matrix{N}_fields ID array.
Fields listed in fieldOrder are placed first in that order. Unlisted fields are appended at the end.
Fieldset open/close fields must be positioned correctly to wrap the fields they group.
Example: reorder_matrix_type_fields(fieldName: "content", matrixTypeName: "panel", fieldOrder: ["body", "section_images", "panel_style_fieldset", "panel_background_color", "panel_inverted", "panel_style_fieldset_END"])
DESC
    )]
    public function reorderMatrixTypeFields(
        string $fieldName,
        string $matrixTypeName,
        #[Schema(type: 'array', description: 'Array of field names in desired order. Unlisted fields are appended at the end.')]
        array $fieldOrder
    ): array {
        try {
            $field = $this->fields()->get($fieldName);
            if (!$field) {
                return $this->error("Field not found: {$fieldName}", 'NOT_FOUND');
            }

            if ($field->type->className() !== 'FieldtypeRepeaterMatrix') {
                return $this->error(
                    "Field '{$fieldName}' is {$field->type->className()}, not FieldtypeRepeaterMatrix.",
                    'INVALID_INPUT'
                );
            }

            // Find the type number
            $data = $field->getArray();
            $n = null;
            for ($i = 0; $i <= 100; $i++) {
                if (isset($data["matrix{$i}_name"]) && $data["matrix{$i}_name"] === $matrixTypeName) {
                    $n = $i;
                    break;
                }
            }

            if ($n === null) {
                return $this->error(
                    "Matrix type '{$matrixTypeName}' not found on field '{$fieldName}'.",
                    'NOT_FOUND'
                );
            }

            // Get current field IDs (may be array or comma-separated string)
            $rawFields = $data["matrix{$n}_fields"] ?? [];
            if (is_string($rawFields)) {
                $currentFieldIds = array_filter(
                    array_map('intval', explode(',', $rawFields)),
                    fn($id) => $id > 0
                );
            } else {
                $currentFieldIds = array_filter(
                    array_map('intval', (array) $rawFields),
                    fn($id) => $id > 0
                );
            }

            // Map current IDs to names
            $currentFieldNames = [];
            $idByName = [];
            foreach ($currentFieldIds as $fId) {
                $f = $this->fields()->get($fId);
                if ($f) {
                    $currentFieldNames[] = $f->name;
                    $idByName[$f->name] = $f->id;
                }
            }

            // Validate requested fields exist in this matrix type
            $notFound = array_diff($fieldOrder, $currentFieldNames);
            if (!empty($notFound)) {
                return $this->error(
                    'Fields not in matrix type: ' . implode(', ', $notFound),
                    'NOT_FOUND'
                );
            }

            // Build final order: requested fields first, then remaining in current order
            $remaining = array_diff($currentFieldNames, $fieldOrder);
            $finalOrder = array_merge($fieldOrder, $remaining);

            // Convert back to IDs
            $newFieldIds = [];
            foreach ($finalOrder as $fName) {
                if (isset($idByName[$fName])) {
                    $newFieldIds[] = $idByName[$fName];
                }
            }

            // 1. Set the field ID array in desired order on the matrix type
            $field->set("matrix{$n}_fields", $newFieldIds);

            // 2. Set sort values in the repeater template's fieldgroup context
            // Both matrix{N}_fields AND fieldgroup context sort must agree
            // for the admin UI to respect the order
            $repeaterTemplate = $field->type->getMatrixTemplate($field, $n);
            if ($repeaterTemplate) {
                $fieldgroup = $repeaterTemplate->fieldgroup;
                $sort = 0;
                foreach ($finalOrder as $fName) {
                    if (isset($idByName[$fName])) {
                        $fieldgroup->setFieldContextArray($idByName[$fName], ['sort' => $sort]);
                        $sort++;
                    }
                }
                $fieldgroup->saveContext();
                $fieldgroup->save();
            }

            // 3. Save the field
            $this->fields()->save($field);

            return $this->success([
                'field' => $fieldName,
                'matrix_type' => $matrixTypeName,
                'field_order' => $finalOrder,
                'count' => count($finalOrder),
            ], "Fields reordered in matrix type '{$matrixTypeName}'");

        } catch (\Throwable $e) {
            return $this->error('Failed to reorder matrix type fields: ' . $e->getMessage());
        }
    }
}
