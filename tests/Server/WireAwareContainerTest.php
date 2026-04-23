<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tests\Server;

use Elabx\ProcessWireMcp\Tests\ProcessWireTestCase;
use Elabx\ProcessWireMcp\Server\WireAwareContainer;
use Elabx\ProcessWireMcp\Tool\Core\FieldTools;

/**
 * Tests that WireAwareContainer refreshes ProcessWire's in-memory caches
 * so that fields/templates created outside the MCP process are visible.
 */
class WireAwareContainerTest extends ProcessWireTestCase
{
    private WireAwareContainer $container;
    private array $createdFields = [];
    private array $createdTemplates = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new WireAwareContainer($this->wire());
    }

    protected function tearDown(): void
    {
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
        foreach (array_reverse($this->createdTemplates) as $name) {
            $template = $this->wire()->wire('templates')->get($name);
            if ($template && $template->id) {
                $fg = $template->fieldgroup;
                $this->wire()->wire('templates')->delete($template);
                $this->wire()->wire('fieldgroups')->delete($fg);
            }
        }
        $this->createdFields = [];
        $this->createdTemplates = [];
        parent::tearDown();
    }

    /**
     * Simulate a field created "outside" the MCP server by inserting directly
     * into the DB via PW's API but bypassing the container. The container's
     * cache refresh should make it visible on the next get() call.
     */
    public function testFieldCreatedExternallyIsVisibleAfterRefresh(): void
    {
        $fieldName = 'mcp_test_external_field';

        // 1. Get FieldTools via container (triggers initial cache load)
        /** @var FieldTools $tools */
        $tools = $this->container->get(FieldTools::class);
        $result = $tools->getField($fieldName);
        $this->assertFalse($result['success'], 'Field should not exist yet');

        // 2. Create the field directly via PW API (simulates admin UI / migration)
        $field = new \ProcessWire\Field();
        $field->name = $fieldName;
        $field->type = $this->wire()->wire('modules')->get('FieldtypeText');
        $field->label = 'External Field';
        $this->wire()->wire('fields')->save($field);
        $this->createdFields[] = $fieldName;

        // 3. Get FieldTools via container again — cache refresh should pick it up
        $tools = $this->container->get(FieldTools::class);
        $result = $tools->getField($fieldName);

        $this->assertTrue($result['success'], 'Field created externally should be visible after container refresh');
        $this->assertEquals($fieldName, $result['data']['name']);
    }

    /**
     * Same test for templates — created outside, visible after refresh.
     */
    public function testTemplateCreatedExternallyIsVisibleAfterRefresh(): void
    {
        $templateName = 'mcp_test_external_template';

        // 1. Verify template doesn't exist via container
        /** @var FieldTools $tools */
        $tools = $this->container->get(FieldTools::class);

        $template = $this->wire()->wire('templates')->get($templateName);
        $this->assertFalse((bool) ($template && $template->id), 'Template should not exist yet');

        // 2. Create template directly via PW API
        $fg = new \ProcessWire\Fieldgroup();
        $fg->name = $templateName;
        $fg->add($this->wire()->wire('fields')->get('title'));
        $this->wire()->wire('fieldgroups')->save($fg);

        $template = new \ProcessWire\Template();
        $template->name = $templateName;
        $template->fieldgroup = $fg;
        $this->wire()->wire('templates')->save($template);
        $this->createdTemplates[] = $templateName;

        // 3. Access container again — triggers refreshWireState()
        $tools = $this->container->get(FieldTools::class);

        // 4. Verify the template is now visible
        $template = $this->wire()->wire('templates')->get($templateName);
        $this->assertNotNull($template);
        $this->assertGreaterThan(0, $template->id, 'Template created externally should be visible after container refresh');
    }
}
