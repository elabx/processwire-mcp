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

    public function testFindFieldsByName(): void
    {
        $result = $this->tools->findFields('name=title');

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['data']['count']);
        $this->assertEquals('title', $result['data']['fields'][0]['name']);
    }

    public function testFindFieldsByNameContains(): void
    {
        $result = $this->tools->findFields('name%=title');

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['data']['count']);
    }

    public function testFindFieldsByTypeShorthand(): void
    {
        // "type=text" should be normalized to "type=FieldtypeText"
        $result = $this->tools->findFields('type=text');

        $this->assertTrue($result['success']);
        // The selector should have been normalized
        $this->assertStringContainsString('FieldtypeText', $result['data']['selector']);
    }

    public function testFindFieldsNoResults(): void
    {
        $result = $this->tools->findFields('name=zzz_nonexistent_field_xyz');

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['data']['count']);
    }

    public function testFindFieldsWithLimit(): void
    {
        $result = $this->tools->findFields('sort=name, limit=2');

        $this->assertTrue($result['success']);
        $this->assertLessThanOrEqual(2, $result['data']['count']);
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

    public function testCreateRepeaterFieldWithSubfields(): void
    {
        if (!$this->wire()->wire('modules')->isInstalled('FieldtypeRepeater')) {
            $this->markTestSkipped('FieldtypeRepeater is not installed.');
        }

        // Create a text field to use inside the repeater
        $this->tools->createField('mcp_test_rep_sub', 'text', 'Repeater Subfield');
        $this->createdFields[] = 'mcp_test_rep_sub';

        // Create a repeater field with repeaterFields
        $result = $this->tools->createField(
            'mcp_test_repeater',
            'FieldtypeRepeater',
            'Test Repeater',
            settings: ['repeaterFields' => ['mcp_test_rep_sub']]
        );
        $this->createdFields[] = 'mcp_test_repeater';

        $this->assertTrue($result['success']);
        $this->assertEquals('mcp_test_repeater', $result['data']['name']);
        $this->assertEquals('FieldtypeRepeater', $result['data']['type']);
        $this->assertArrayHasKey('repeater', $result['data']);
        $this->assertContains('mcp_test_rep_sub', $result['data']['repeater']['fields_added']);
    }

    public function testCreateRepeaterFieldWithoutSubfields(): void
    {
        if (!$this->wire()->wire('modules')->isInstalled('FieldtypeRepeater')) {
            $this->markTestSkipped('FieldtypeRepeater is not installed.');
        }

        // Create a repeater field without repeaterFields — no repeater key expected
        $result = $this->tools->createField(
            'mcp_test_rep_bare',
            'FieldtypeRepeater',
            'Bare Repeater'
        );
        $this->createdFields[] = 'mcp_test_rep_bare';

        $this->assertTrue($result['success']);
        $this->assertEquals('FieldtypeRepeater', $result['data']['type']);
        $this->assertArrayNotHasKey('repeater', $result['data']);
    }

    public function testUpdateFieldAddsRepeaterSubfields(): void
    {
        if (!$this->wire()->wire('modules')->isInstalled('FieldtypeRepeater')) {
            $this->markTestSkipped('FieldtypeRepeater is not installed.');
        }

        // Create subfield and repeater field
        $this->tools->createField('mcp_test_rep_sub2', 'text', 'Sub 2');
        $this->createdFields[] = 'mcp_test_rep_sub2';

        $this->tools->createField(
            'mcp_test_rep_upd',
            'FieldtypeRepeater',
            'Repeater For Update'
        );
        $this->createdFields[] = 'mcp_test_rep_upd';

        // Update the repeater to add subfields
        $result = $this->tools->updateField(
            'mcp_test_rep_upd',
            settings: ['repeaterFields' => ['mcp_test_rep_sub2']]
        );

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('repeater', $result['data']);
        $this->assertContains('mcp_test_rep_sub2', $result['data']['repeater']['fields_added']);
    }
}
