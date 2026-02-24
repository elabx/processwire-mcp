<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tests\Tool;

use Elabx\ProcessWireMcp\Tests\ProcessWireTestCase;
use Elabx\ProcessWireMcp\Tool\Core\FieldTools;

class FieldToolsTest extends ProcessWireTestCase
{
    private FieldTools $tools;

    private array $createdFields = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new FieldTools();
        $this->tools->setWire($this->wire());
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->createdFields) as $name) {
            $field = $this->wire()->wire('fields')->get($name);
            if ($field && $field->id) {
                // Remove from all templates first
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

    public function testListFields(): void
    {
        // Include system fields since a default install may only have system fields
        $result = $this->tools->listFields(includeSystem: true);

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['data']['count']);
    }

    public function testGetField(): void
    {
        $result = $this->tools->getField('title');

        $this->assertTrue($result['success']);
        $this->assertEquals('title', $result['data']['name']);
        $this->assertArrayHasKey('type', $result['data']);
    }

    public function testGetFieldNotFound(): void
    {
        $result = $this->tools->getField('nonexistent_field');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testListFieldTypes(): void
    {
        $result = $this->tools->listFieldTypes();

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['data']['count']);
    }

    public function testCreateField(): void
    {
        $result = $this->tools->createField('mcp_test_field', 'text', 'Test Field');
        $this->createdFields[] = 'mcp_test_field';

        $this->assertTrue($result['success']);
        $this->assertEquals('mcp_test_field', $result['data']['name']);
        $this->assertEquals('FieldtypeText', $result['data']['type']);
    }

    public function testCreateFieldAlreadyExists(): void
    {
        $this->tools->createField('mcp_test_field_dup', 'text', 'Dup Field');
        $this->createdFields[] = 'mcp_test_field_dup';

        $result = $this->tools->createField('mcp_test_field_dup', 'text', 'Dup Field');

        $this->assertFalse($result['success']);
    }

    public function testUpdateField(): void
    {
        $this->tools->createField('mcp_test_field_upd', 'text', 'Original Label');
        $this->createdFields[] = 'mcp_test_field_upd';

        $result = $this->tools->updateField('mcp_test_field_upd', label: 'Updated Label');

        $this->assertTrue($result['success']);
        $this->assertEquals('Updated Label', $result['data']['label']);
    }

    public function testUpdateFieldNotFound(): void
    {
        $result = $this->tools->updateField('nonexistent_field_xyz', label: 'X');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testDeleteField(): void
    {
        $this->tools->createField('mcp_test_field_del', 'text', 'Delete Me');
        $this->createdFields[] = 'mcp_test_field_del';

        $result = $this->tools->deleteField('mcp_test_field_del');

        $this->assertTrue($result['success']);
        // Remove from createdFields since it's already deleted
        $this->createdFields = array_diff($this->createdFields, ['mcp_test_field_del']);
    }

    public function testDeleteFieldNotFound(): void
    {
        $result = $this->tools->deleteField('nonexistent_field_xyz');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testCloneField(): void
    {
        $this->tools->createField('mcp_test_field_src', 'text', 'Source Field');
        $this->createdFields[] = 'mcp_test_field_src';

        $result = $this->tools->cloneField('mcp_test_field_src', 'mcp_test_field_clone');
        $this->createdFields[] = 'mcp_test_field_clone';

        $this->assertTrue($result['success']);
        $this->assertEquals('mcp_test_field_src', $result['data']['source']);
    }

    public function testCloneFieldSourceNotFound(): void
    {
        $result = $this->tools->cloneField('nonexistent_field_xyz', 'mcp_test_clone');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }
}
