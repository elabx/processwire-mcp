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

            $typesInfo = $field->type->getMatrixTypesInfo($field);
            $types = [];

            foreach ($typesInfo as $typeName => $info) {
                $typeData = [
                    'n' => $info['type'] ?? 0,
                    'name' => $info['name'] ?? $typeName,
                    'label' => $info['label'] ?? '',
                    'sort' => $info['sort'] ?? 0,
                    'head' => $info['head'] ?? '',
                    'fields' => [],
                ];

                // Get fields for this matrix type
                // $info['fields'] is array<string, Field> (name => Field object)
                if (!empty($info['fields'])) {
                    foreach ($info['fields'] as $fName => $f) {
                        if (!$f || !($f instanceof \ProcessWire\Field)) continue;

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
            $newItem = $items->getNewItem();
            $newItem->setMatrixType($matrixType);

            // Set field values
            $set = [];
            $skipped = [];

            foreach ($fieldValues as $fName => $fValue) {
                if ($newItem->hasField($fName)) {
                    $newItem->set($fName, $fValue);
                    $set[] = $fName;
                } else {
                    $skipped[] = $fName;
                }
            }

            $newItem->save();
            $pageObj->save($fieldName);

            $result = [
                'id' => $newItem->id,
                'matrix_type' => $newItem->matrix('name'),
                'set_fields' => $set,
            ];

            if (!empty($skipped)) {
                $result['skipped_fields'] = $skipped;
            }

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
                if ($item->hasField($fName)) {
                    $item->set($fName, $fValue);
                    $updated[] = $fName;
                } else {
                    $skipped[] = $fName;
                }
            }

            $item->save();

            $result = [
                'id' => $item->id,
                'matrix_type' => $item->matrix('name'),
                'updated_fields' => $updated,
            ];

            if (!empty($skipped)) {
                $result['skipped_fields'] = $skipped;
            }

            return $this->success($result, 'Matrix item updated successfully');

        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'ACCESS_DENIED');
        } catch (\Throwable $e) {
            return $this->error('Failed to update matrix item: ' . $e->getMessage());
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
}
