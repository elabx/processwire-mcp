<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tests\Tool;

use Elabx\ProcessWireMcp\Tests\ProcessWireTestCase;
use Elabx\ProcessWireMcp\Tool\Core\TemplateTools;

class TemplateToolsTest extends ProcessWireTestCase
{
    private TemplateTools $tools;

    private array $createdTemplates = [];

    private array $createdFields = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new TemplateTools();
        $this->tools->setWire($this->wire());
    }

    protected function tearDown(): void
    {
        // Delete templates first (reverse order)
        foreach (array_reverse($this->createdTemplates) as $name) {
            $t = $this->wire()->wire('templates')->get($name);
            if ($t && $t->id) {
                $fg = $t->fieldgroup;
                $this->wire()->wire('templates')->delete($t);
                if ($fg) {
                    $this->wire()->wire('fieldgroups')->delete($fg);
                }
            }
        }
        $this->createdTemplates = [];

        // Then delete fields
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

    public function testFindTemplatesByName(): void
    {
        $result = $this->tools->findTemplates('name=basic-page');

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['data']['count']);
        $this->assertEquals('basic-page', $result['data']['templates'][0]['name']);
    }

    public function testFindTemplatesByNameContains(): void
    {
        $result = $this->tools->findTemplates('name%=basic');

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['data']['count']);
    }

    public function testFindTemplatesNoResults(): void
    {
        $result = $this->tools->findTemplates('name=zzz_nonexistent_template_xyz');

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['data']['count']);
    }

    public function testFindTemplatesWithLimit(): void
    {
        $result = $this->tools->findTemplates('sort=name, limit=2');

        $this->assertTrue($result['success']);
        $this->assertLessThanOrEqual(2, $result['data']['count']);
    }

    public function testFindTemplatesSystemFlag(): void
    {
        $result = $this->tools->findTemplates('name=admin');

        $this->assertTrue($result['success']);
        if ($result['data']['count'] > 0) {
            $this->assertTrue($result['data']['templates'][0]['is_system']);
        }
    }

    public function testListTemplates(): void
    {
        $result = $this->tools->listTemplates();

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['data']['count']);
    }

    public function testListTemplatesExcludesSystemByDefault(): void
    {
        $result = $this->tools->listTemplates(false);
        $names = array_column($result['data']['templates'], 'name');

        $this->assertNotContains('admin', $names);
    }

    public function testListTemplatesIncludesSystem(): void
    {
        $result = $this->tools->listTemplates(true);
        $names = array_column($result['data']['templates'], 'name');

        $this->assertContains('admin', $names);
    }

    public function testGetTemplateFields(): void
    {
        $result = $this->tools->getTemplateFields('basic-page');

        $this->assertTrue($result['success']);
        $this->assertEquals('basic-page', $result['data']['name']);
        $this->assertArrayHasKey('fields', $result['data']);
        $this->assertGreaterThan(0, $result['data']['field_count']);
    }

    public function testGetTemplateFieldsNotFound(): void
    {
        $result = $this->tools->getTemplateFields('nonexistent-template');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testGetTemplateFile(): void
    {
        $result = $this->tools->getTemplateFile('basic-page');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('template', $result['data']);
        $this->assertArrayHasKey('filename', $result['data']);
        $this->assertArrayHasKey('path', $result['data']);
        $this->assertArrayHasKey('exists', $result['data']);
    }

    public function testGetTemplateFileNotFound(): void
    {
        $result = $this->tools->getTemplateFile('nonexistent_template_xyz');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testCreateTemplate(): void
    {
        $result = $this->tools->createTemplate('mcp_test_template', 'Test Template');
        $this->createdTemplates[] = 'mcp_test_template';

        $this->assertTrue($result['success']);
        $this->assertEquals('mcp_test_template', $result['data']['name']);
    }

    public function testCreateTemplateAlreadyExists(): void
    {
        $this->tools->createTemplate('mcp_test_tpl_dup', 'Dup Template');
        $this->createdTemplates[] = 'mcp_test_tpl_dup';

        $result = $this->tools->createTemplate('mcp_test_tpl_dup', 'Dup Template');

        $this->assertFalse($result['success']);
    }

    public function testUpdateTemplate(): void
    {
        $this->tools->createTemplate('mcp_test_tpl_upd', 'Original Label');
        $this->createdTemplates[] = 'mcp_test_tpl_upd';

        $result = $this->tools->updateTemplate('mcp_test_tpl_upd', label: 'Updated Label');

        $this->assertTrue($result['success']);
        $this->assertEquals('Updated Label', $result['data']['label']);
    }

    public function testUpdateTemplateNotFound(): void
    {
        $result = $this->tools->updateTemplate('nonexistent_template_xyz', label: 'X');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testDeleteTemplate(): void
    {
        $this->tools->createTemplate('mcp_test_tpl_del', 'Delete Me');
        $this->createdTemplates[] = 'mcp_test_tpl_del';

        $result = $this->tools->deleteTemplate('mcp_test_tpl_del');

        $this->assertTrue($result['success']);
        // Remove from createdTemplates since it's already deleted
        $this->createdTemplates = array_diff($this->createdTemplates, ['mcp_test_tpl_del']);
    }

    public function testDeleteTemplateNotFound(): void
    {
        $result = $this->tools->deleteTemplate('nonexistent_template_xyz');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testCloneTemplate(): void
    {
        $result = $this->tools->cloneTemplate('basic-page', 'mcp_test_tpl_clone');
        $this->createdTemplates[] = 'mcp_test_tpl_clone';

        $this->assertTrue($result['success']);
        $this->assertEquals('basic-page', $result['data']['source']);
    }

    public function testCloneTemplateSourceNotFound(): void
    {
        $result = $this->tools->cloneTemplate('nonexistent_template_xyz', 'mcp_test_clone');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testAddFieldToTemplate(): void
    {
        // Create a field via PW API
        $f = new \ProcessWire\Field();
        $f->type = $this->wire()->wire('fieldtypes')->get('FieldtypeText');
        $f->name = 'mcp_test_tpl_field';
        $this->wire()->wire('fields')->save($f);
        $this->createdFields[] = 'mcp_test_tpl_field';

        // Create a template via the tools
        $this->tools->createTemplate('mcp_test_tpl_af');
        $this->createdTemplates[] = 'mcp_test_tpl_af';

        $result = $this->tools->addFieldToTemplate('mcp_test_tpl_af', 'mcp_test_tpl_field');

        $this->assertTrue($result['success']);
    }

    public function testRemoveFieldFromTemplate(): void
    {
        // Create a field via PW API
        $f = new \ProcessWire\Field();
        $f->type = $this->wire()->wire('fieldtypes')->get('FieldtypeText');
        $f->name = 'mcp_test_tpl_field2';
        $this->wire()->wire('fields')->save($f);
        $this->createdFields[] = 'mcp_test_tpl_field2';

        // Create a template via the tools
        $this->tools->createTemplate('mcp_test_tpl_rf');
        $this->createdTemplates[] = 'mcp_test_tpl_rf';

        // Add the field first
        $this->tools->addFieldToTemplate('mcp_test_tpl_rf', 'mcp_test_tpl_field2');

        // Now remove it
        $result = $this->tools->removeFieldFromTemplate('mcp_test_tpl_rf', 'mcp_test_tpl_field2');

        $this->assertTrue($result['success']);
    }
}
