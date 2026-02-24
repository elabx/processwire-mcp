<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tests\Tool;

use Elabx\ProcessWireMcp\Tests\ProcessWireTestCase;
use Elabx\ProcessWireMcp\Tool\Core\PageTools;

class PageToolsTest extends ProcessWireTestCase
{
    private PageTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new PageTools();
        $this->tools->setWire($this->wire());
    }

    public function testFindPagesReturnsResults(): void
    {
        $result = $this->tools->findPages('limit=3');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('pages', $result['data']);
        $this->assertLessThanOrEqual(3, count($result['data']['pages']));
    }

    public function testFindPagesExcludesAdmin(): void
    {
        $result = $this->tools->findPages('id=2');

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['data']['count']);
    }

    public function testGetPageById(): void
    {
        $result = $this->tools->getPage(1);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['data']['id']);
        $this->assertEquals('/', $result['data']['path']);
    }

    public function testGetPageBlocksAdmin(): void
    {
        $result = $this->tools->getPage(2);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('ACCESS_DENIED', $result['code'] ?? '');
    }

    public function testGetPageNotFound(): void
    {
        $result = $this->tools->getPage(999999);

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testGetChildren(): void
    {
        // Default profile homepage may have no non-admin children
        $result = $this->tools->getChildren(1);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('children', $result['data']);
        $this->assertGreaterThanOrEqual(0, $result['data']['count']);
    }
}
