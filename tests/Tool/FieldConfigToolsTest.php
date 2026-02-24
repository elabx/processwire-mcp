<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tests\Tool;

use Elabx\ProcessWireMcp\Tests\ProcessWireTestCase;
use Elabx\ProcessWireMcp\Tool\Core\FieldConfigTools;

class FieldConfigToolsTest extends ProcessWireTestCase
{
    private FieldConfigTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new FieldConfigTools();
        $this->tools->setWire($this->wire());
    }

    public function testGetFieldConfigGlobal(): void
    {
        $result = $this->tools->getFieldConfig('title');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('field', $result['data']);
        $this->assertArrayHasKey('context', $result['data']);
        $this->assertArrayHasKey('label', $result['data']);
        $this->assertArrayHasKey('type', $result['data']);
        $this->assertEquals('global', $result['data']['context']);
    }

    public function testGetFieldConfigNotFound(): void
    {
        $result = $this->tools->getFieldConfig('nonexistent_field_xyz');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testGetFieldConfigTemplateContext(): void
    {
        $result = $this->tools->getFieldConfig('title', 'basic-page');

        $this->assertTrue($result['success']);
        $this->assertEquals('template:basic-page', $result['data']['context']);
    }

    public function testGetFieldConfigTemplateNotFound(): void
    {
        $result = $this->tools->getFieldConfig('title', 'nonexistent_template_xyz');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testGetFieldOptionsNotFound(): void
    {
        $result = $this->tools->getFieldOptions('nonexistent_field_xyz');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testGetFieldOptionsNotOptionsField(): void
    {
        $result = $this->tools->getFieldOptions('title');

        $this->assertFalse($result['success']);
        $this->assertEquals('INVALID_INPUT', $result['code']);
    }
}
