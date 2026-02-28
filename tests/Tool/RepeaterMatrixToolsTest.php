<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tests\Tool;

use Elabx\ProcessWireMcp\Tests\ProcessWireTestCase;
use Elabx\ProcessWireMcp\Tool\Core\RepeaterMatrixTools;
use Elabx\ProcessWireMcp\Tool\Core\FieldTools;

class RepeaterMatrixToolsTest extends ProcessWireTestCase
{
    private RepeaterMatrixTools $tools;

    private FieldTools $fieldTools;

    private array $createdFields = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new RepeaterMatrixTools();
        $this->tools->setWire($this->wire());
        $this->fieldTools = new FieldTools();
        $this->fieldTools->setWire($this->wire());
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->createdFields) as $name) {
            $field = $this->wire()->wire('fields')->get($name);
            if ($field && $field->id) {
                foreach ($this->wire()->wire('templates') as $template) {
                    if ($template->fieldgroup->hasField($field)) {
                        $template->fieldgroup->remove($field);
                        $template->fieldgroup->save();
                    }
                }
                $this->wire()->wire('fields')->delete($field);
            }
        }
        $this->createdFields = [];
        parent::tearDown();
    }

    /**
     * Helper: check if FieldtypeRepeaterMatrix is available.
     */
    private function repeaterMatrixAvailable(): bool
    {
        return $this->wire()->wire('modules')->isInstalled('FieldtypeRepeaterMatrix');
    }

    // ── getMatrixTypes ───────────────────────────────────────────

    public function testGetMatrixTypesFieldNotFound(): void
    {
        $result = $this->tools->getMatrixTypes('nonexistent_field_xyz');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testGetMatrixTypesWrongFieldType(): void
    {
        // 'title' is FieldtypePageTitle, not RepeaterMatrix
        $result = $this->tools->getMatrixTypes('title');

        $this->assertFalse($result['success']);
        $this->assertEquals('INVALID_INPUT', $result['code']);
    }

    // ── getMatrixItems ───────────────────────────────────────────

    public function testGetMatrixItemsPageNotFound(): void
    {
        $result = $this->tools->getMatrixItems(999999, 'some_field');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testGetMatrixItemsFieldNotFound(): void
    {
        $result = $this->tools->getMatrixItems(1, 'nonexistent_field_xyz');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testGetMatrixItemsWrongFieldType(): void
    {
        $result = $this->tools->getMatrixItems(1, 'title');

        $this->assertFalse($result['success']);
        $this->assertEquals('INVALID_INPUT', $result['code']);
    }

    // ── addMatrixItem ────────────────────────────────────────────

    public function testAddMatrixItemPageNotFound(): void
    {
        $result = $this->tools->addMatrixItem(999999, 'some_field', 'text_block');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testAddMatrixItemFieldNotFound(): void
    {
        $result = $this->tools->addMatrixItem(1, 'nonexistent_field_xyz', 'text_block');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testAddMatrixItemWrongFieldType(): void
    {
        $result = $this->tools->addMatrixItem(1, 'title', 'text_block');

        $this->assertFalse($result['success']);
        $this->assertEquals('INVALID_INPUT', $result['code']);
    }

    // ── updateMatrixItem ─────────────────────────────────────────

    public function testUpdateMatrixItemNotFound(): void
    {
        $result = $this->tools->updateMatrixItem(999999, ['headline' => 'Test']);

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testUpdateMatrixItemNotRepeaterPage(): void
    {
        // Page 1 (home) is not a repeater page
        $result = $this->tools->updateMatrixItem(1, ['title' => 'Test']);

        $this->assertFalse($result['success']);
        $this->assertEquals('INVALID_INPUT', $result['code']);
    }

    // ── deleteMatrixItem ─────────────────────────────────────────

    public function testDeleteMatrixItemNotFound(): void
    {
        $result = $this->tools->deleteMatrixItem(999999);

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testDeleteMatrixItemNotRepeaterPage(): void
    {
        $result = $this->tools->deleteMatrixItem(1);

        $this->assertFalse($result['success']);
        $this->assertEquals('INVALID_INPUT', $result['code']);
    }

    // ── createMatrixType ─────────────────────────────────────────

    public function testCreateMatrixTypeFieldNotFound(): void
    {
        $result = $this->tools->createMatrixType(
            'nonexistent_field_xyz',
            'text_block',
            'Text Block',
            ['title']
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testCreateMatrixTypeWrongFieldType(): void
    {
        $result = $this->tools->createMatrixType(
            'title',
            'text_block',
            'Text Block',
            ['title']
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('INVALID_INPUT', $result['code']);
    }

    public function testCreateMatrixTypeWithNonexistentFields(): void
    {
        if (!$this->repeaterMatrixAvailable()) {
            $this->markTestSkipped('FieldtypeRepeaterMatrix is not installed.');
        }

        // Create a RepeaterMatrix field for this test
        $result = $this->fieldTools->createField('mcp_test_rm', 'FieldtypeRepeaterMatrix', 'Test RM');
        $this->createdFields[] = 'mcp_test_rm';

        if (!$result['success']) {
            $this->markTestSkipped('Could not create RepeaterMatrix field for test.');
        }

        $result = $this->tools->createMatrixType(
            'mcp_test_rm',
            'text_block',
            'Text Block',
            ['nonexistent_field_abc']
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    // ── updateMatrixType ─────────────────────────────────────────

    public function testUpdateMatrixTypeFieldNotFound(): void
    {
        $result = $this->tools->updateMatrixType(
            'nonexistent_field_xyz',
            'text_block',
            label: 'Updated Label'
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testUpdateMatrixTypeWrongFieldType(): void
    {
        $result = $this->tools->updateMatrixType(
            'title',
            'text_block',
            label: 'Updated Label'
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('INVALID_INPUT', $result['code']);
    }

    // ── deleteMatrixType ─────────────────────────────────────────

    public function testDeleteMatrixTypeFieldNotFound(): void
    {
        $result = $this->tools->deleteMatrixType(
            'nonexistent_field_xyz',
            'text_block'
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testDeleteMatrixTypeWrongFieldType(): void
    {
        $result = $this->tools->deleteMatrixType(
            'title',
            'text_block'
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('INVALID_INPUT', $result['code']);
    }
}
