<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tests\Tool;

use Elabx\ProcessWireMcp\Tests\ProcessWireTestCase;
use Elabx\ProcessWireMcp\Tool\Core\ModuleTools;

class ModuleToolsTest extends ProcessWireTestCase
{
    private ModuleTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new ModuleTools();
        $this->tools->setWire($this->wire());
    }

    public function testListModulesAll(): void
    {
        $result = $this->tools->listModules();

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['data']['count']);
        $this->assertArrayHasKey('className', $result['data']['modules'][0]);
        $this->assertArrayHasKey('installed', $result['data']['modules'][0]);
    }

    public function testListModulesInstalledOnly(): void
    {
        $result = $this->tools->listModules(true);

        $this->assertTrue($result['success']);
        foreach ($result['data']['modules'] as $module) {
            $this->assertTrue($module['installed']);
        }
    }

    public function testGetModuleInfo(): void
    {
        $result = $this->tools->getModuleInfo('ProcessHome');

        $this->assertTrue($result['success']);
        $this->assertEquals('ProcessHome', $result['data']['className']);
        $this->assertArrayHasKey('installed', $result['data']);
    }

    public function testGetModuleInfoNotFound(): void
    {
        $result = $this->tools->getModuleInfo('NonExistentModuleXyz');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testInstallModuleWithoutConfirm(): void
    {
        $result = $this->tools->installModule('ProcessHome');

        $this->assertFalse($result['success']);
    }

    public function testInstallModuleAlreadyInstalled(): void
    {
        $result = $this->tools->installModule('ProcessHome', true);

        $this->assertFalse($result['success']);
    }

    public function testUninstallModuleWithoutConfirm(): void
    {
        $result = $this->tools->uninstallModule('ProcessHome');

        $this->assertFalse($result['success']);
    }

    public function testUninstallModuleNotInstalled(): void
    {
        $result = $this->tools->uninstallModule('NonExistentModuleXyz', true);

        $this->assertFalse($result['success']);
    }

    public function testGetModuleConfigNotInstalled(): void
    {
        $result = $this->tools->getModuleConfig('NonExistentModuleXyz');

        $this->assertFalse($result['success']);
    }

    public function testSaveModuleConfigNotInstalled(): void
    {
        $result = $this->tools->saveModuleConfig('NonExistentModuleXyz', ['key' => 'value']);

        $this->assertFalse($result['success']);
    }
}
