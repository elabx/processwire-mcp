<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tests\Tool;

use Elabx\ProcessWireMcp\Tests\ProcessWireTestCase;
use Elabx\ProcessWireMcp\Tool\Core\FileTools;

class FileToolsTest extends ProcessWireTestCase
{
    private FileTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new FileTools();
        $this->tools->setWire($this->wire());
    }

    public function testGetPageFilesHomepage(): void
    {
        $result = $this->tools->getPageFiles(1);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('page', $result['data']);
        $this->assertArrayHasKey('field_count', $result['data']);
        $this->assertArrayHasKey('total_files', $result['data']);
        $this->assertArrayHasKey('fields', $result['data']);
    }

    public function testGetPageFilesNotFound(): void
    {
        $result = $this->tools->getPageFiles(999999);

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testGetPageFilesAdminBlocked(): void
    {
        $result = $this->tools->getPageFiles(2);

        $this->assertFalse($result['success']);
        $this->assertEquals('ACCESS_DENIED', $result['code']);
    }

    public function testGetImageVariationsNotFound(): void
    {
        $result = $this->tools->getImageVariations(999999, 'images', 'test.jpg');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testGetImageVariationsAdminBlocked(): void
    {
        $result = $this->tools->getImageVariations(2, 'images', 'test.jpg');

        $this->assertFalse($result['success']);
        $this->assertEquals('ACCESS_DENIED', $result['code']);
    }

    public function testGetPageFilesPathHomepage(): void
    {
        $result = $this->tools->getPageFilesPath(1);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('path', $result['data']);
        $this->assertArrayHasKey('url', $result['data']);
        $this->assertArrayHasKey('exists', $result['data']);
    }

    public function testGetPageFilesPathNotFound(): void
    {
        $result = $this->tools->getPageFilesPath(999999);

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testGetPageFilesPathAdminBlocked(): void
    {
        $result = $this->tools->getPageFilesPath(2);

        $this->assertFalse($result['success']);
        $this->assertEquals('ACCESS_DENIED', $result['code']);
    }
}
