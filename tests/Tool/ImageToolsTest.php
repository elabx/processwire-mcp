<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tests\Tool;

use Elabx\ProcessWireMcp\Tests\ProcessWireTestCase;
use Elabx\ProcessWireMcp\Tool\Core\ImageTools;

class ImageToolsTest extends ProcessWireTestCase
{
    private ImageTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new ImageTools();
        $this->tools->setWire($this->wire());
    }

    public function testCopyPageImageSourceNotFound(): void
    {
        $result = $this->tools->copyPageImage(999999, 'images', 1, 'images');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testCopyPageImageTargetNotFound(): void
    {
        $result = $this->tools->copyPageImage(1, 'images', 999999, 'images');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testCopyPageImageAdminBlocked(): void
    {
        $result = $this->tools->copyPageImage(2, 'images', 1, 'images');

        $this->assertFalse($result['success']);
        $this->assertEquals('ACCESS_DENIED', $result['code']);
    }
}
