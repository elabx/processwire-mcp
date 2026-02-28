<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tests\Tool;

use Elabx\ProcessWireMcp\Tests\ProcessWireTestCase;
use Elabx\ProcessWireMcp\Tool\Core\FormBuilderTools;

class FormBuilderToolsTest extends ProcessWireTestCase
{
    private FormBuilderTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new FormBuilderTools();
        $this->tools->setWire($this->wire());
    }

    /**
     * Helper: check if FormBuilder is installed in this environment.
     */
    private function formBuilderInstalled(): bool
    {
        return $this->wire()->wire('modules')->isInstalled('FormBuilder');
    }

    // ── listForms ────────────────────────────────────────────────

    public function testListFormsNotAvailable(): void
    {
        if ($this->formBuilderInstalled()) {
            $this->markTestSkipped('FormBuilder is installed; cannot test NOT_AVAILABLE path.');
        }

        $result = $this->tools->listForms();

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_AVAILABLE', $result['code']);
    }

    public function testListFormsWhenInstalled(): void
    {
        if (!$this->formBuilderInstalled()) {
            $this->markTestSkipped('FormBuilder is not installed.');
        }

        $result = $this->tools->listForms();

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('count', $result['data']);
        $this->assertArrayHasKey('forms', $result['data']);
    }

    // ── getForm ──────────────────────────────────────────────────

    public function testGetFormNotAvailable(): void
    {
        if ($this->formBuilderInstalled()) {
            $this->markTestSkipped('FormBuilder is installed; cannot test NOT_AVAILABLE path.');
        }

        $result = $this->tools->getForm('contact');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_AVAILABLE', $result['code']);
    }

    // ── createForm ───────────────────────────────────────────────

    public function testCreateFormNotAvailable(): void
    {
        if ($this->formBuilderInstalled()) {
            $this->markTestSkipped('FormBuilder is installed; cannot test NOT_AVAILABLE path.');
        }

        $result = $this->tools->createForm('mcp_test_form');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_AVAILABLE', $result['code']);
    }

    // ── deleteForm ───────────────────────────────────────────────

    public function testDeleteFormRequiresConfirmation(): void
    {
        $result = $this->tools->deleteForm('some_form', false);

        $this->assertFalse($result['success']);
        $this->assertEquals('CONFIRMATION_REQUIRED', $result['code']);
    }

    public function testDeleteFormNotAvailable(): void
    {
        if ($this->formBuilderInstalled()) {
            $this->markTestSkipped('FormBuilder is installed; cannot test NOT_AVAILABLE path.');
        }

        $result = $this->tools->deleteForm('some_form', true);

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_AVAILABLE', $result['code']);
    }
}
