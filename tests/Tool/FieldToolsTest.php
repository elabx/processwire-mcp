<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tests\Tool;

use Elabx\ProcessWireMcp\Tests\ProcessWireTestCase;
use Elabx\ProcessWireMcp\Tool\Core\FieldTools;

class FieldToolsTest extends ProcessWireTestCase
{
    private FieldTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new FieldTools();
        $this->tools->setWire($this->wire());
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
}
