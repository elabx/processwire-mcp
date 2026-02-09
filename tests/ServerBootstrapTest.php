<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tests;

use Elabx\ProcessWireMcp\Bootstrap\ProcessWireBootstrap;
use ProcessWire\ProcessWire;

class ServerBootstrapTest extends ProcessWireTestCase
{
    public function testProcessWireBooted(): void
    {
        $this->assertInstanceOf(ProcessWire::class, $this->wire());
    }

    public function testPagesApiAvailable(): void
    {
        $pages = $this->wire()->wire('pages');
        $this->assertNotNull($pages);
    }

    public function testHomepageExists(): void
    {
        $home = $this->wire()->wire('pages')->get('/');
        $this->assertGreaterThan(0, $home->id);
    }
}
