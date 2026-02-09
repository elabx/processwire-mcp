<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tests\Tool;

use Elabx\ProcessWireMcp\Tests\ProcessWireTestCase;
use Elabx\ProcessWireMcp\Tool\Core\TemplateTools;

class TemplateToolsTest extends ProcessWireTestCase
{
    private TemplateTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new TemplateTools();
        $this->tools->setWire($this->wire());
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
}
