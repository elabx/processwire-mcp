<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tests\Tool;

use Elabx\ProcessWireMcp\Tests\ProcessWireTestCase;
use Elabx\ProcessWireMcp\Tool\Core\PageTools;

class PageToolsTest extends ProcessWireTestCase
{
    private PageTools $tools;

    private array $createdPageIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new PageTools();
        $this->tools->setWire($this->wire());
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->createdPageIds) as $id) {
            $page = $this->wire()->wire('pages')->get($id);
            if ($page && $page->id) {
                // If trashed, we still need to delete
                $this->wire()->wire('pages')->delete($page, true);
            }
        }
        $this->createdPageIds = [];
        parent::tearDown();
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

    public function testCreatePage(): void
    {
        $result = $this->tools->createPage('basic-page', 1, 'MCP Test Page');

        $this->assertTrue($result['success']);
        $this->createdPageIds[] = $result['data']['id'];
        $this->assertEquals('basic-page', $result['data']['template']);
        $this->assertEquals(1, $result['data']['parent_id']);
    }

    public function testCreatePageAdminBlocked(): void
    {
        $result = $this->tools->createPage('basic-page', 2, 'Admin Child');

        $this->assertFalse($result['success']);
        $this->assertEquals('ACCESS_DENIED', $result['code']);
    }

    public function testCreatePageTemplateNotFound(): void
    {
        $result = $this->tools->createPage('nonexistent_template_xyz', 1, 'Test');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testUpdatePage(): void
    {
        $createResult = $this->tools->createPage('basic-page', 1, 'MCP Update Test');
        $this->assertTrue($createResult['success']);
        $id = $createResult['data']['id'];
        $this->createdPageIds[] = $id;

        $result = $this->tools->updatePage($id, ['title' => 'Updated Title']);

        $this->assertTrue($result['success']);
        $this->assertEquals('Updated Title', $result['data']['title']);
    }

    public function testUpdatePageNotFound(): void
    {
        $result = $this->tools->updatePage(999999, ['title' => 'X']);

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testUpdatePageAdminBlocked(): void
    {
        $result = $this->tools->updatePage(2, ['title' => 'X']);

        $this->assertFalse($result['success']);
        $this->assertEquals('ACCESS_DENIED', $result['code']);
    }

    public function testDeletePageToTrash(): void
    {
        $createResult = $this->tools->createPage('basic-page', 1, 'MCP Trash Test');
        $this->assertTrue($createResult['success']);
        $id = $createResult['data']['id'];
        $this->createdPageIds[] = $id;

        $result = $this->tools->deletePage($id);

        $this->assertTrue($result['success']);
        $this->assertEquals('trashed', $result['data']['action']);
    }

    public function testDeletePagePermanently(): void
    {
        $createResult = $this->tools->createPage('basic-page', 1, 'MCP Perm Delete Test');
        $this->assertTrue($createResult['success']);
        $id = $createResult['data']['id'];
        // Do NOT add to createdPageIds since it will be permanently deleted

        $result = $this->tools->deletePage($id, true);

        $this->assertTrue($result['success']);
        $this->assertEquals('permanently_deleted', $result['data']['action']);
    }

    public function testDeletePageNotFound(): void
    {
        $result = $this->tools->deletePage(999999);

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testDeletePageAdminBlocked(): void
    {
        $result = $this->tools->deletePage(2);

        $this->assertFalse($result['success']);
        $this->assertEquals('ACCESS_DENIED', $result['code']);
    }

    public function testClonePage(): void
    {
        $createResult = $this->tools->createPage('basic-page', 1, 'MCP Clone Source');
        $this->assertTrue($createResult['success']);
        $id = $createResult['data']['id'];
        $this->createdPageIds[] = $id;

        $result = $this->tools->clonePage($id);

        $this->assertTrue($result['success']);
        $this->createdPageIds[] = $result['data']['id'];
        $this->assertNotEquals($id, $result['data']['id']);
    }

    public function testClonePageNotFound(): void
    {
        $result = $this->tools->clonePage(999999);

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testRestorePage(): void
    {
        $createResult = $this->tools->createPage('basic-page', 1, 'MCP Restore Test');
        $this->assertTrue($createResult['success']);
        $id = $createResult['data']['id'];
        $this->createdPageIds[] = $id;

        // Trash it first
        $trashResult = $this->tools->deletePage($id);
        $this->assertTrue($trashResult['success']);
        $this->assertEquals('trashed', $trashResult['data']['action']);

        // Now restore it
        $result = $this->tools->restorePage($id);

        $this->assertTrue($result['success']);
    }

    public function testRestorePageNotInTrash(): void
    {
        $result = $this->tools->restorePage(1);

        $this->assertFalse($result['success']);
        $this->assertEquals('INVALID_INPUT', $result['code']);
    }

    public function testSortPages(): void
    {
        $createResult1 = $this->tools->createPage('basic-page', 1, 'MCP Sort Test 1');
        $this->assertTrue($createResult1['success']);
        $id1 = $createResult1['data']['id'];
        $this->createdPageIds[] = $id1;

        $createResult2 = $this->tools->createPage('basic-page', 1, 'MCP Sort Test 2');
        $this->assertTrue($createResult2['success']);
        $id2 = $createResult2['data']['id'];
        $this->createdPageIds[] = $id2;

        $result = $this->tools->sortPages($id2, $id1, 'before');

        $this->assertTrue($result['success']);
    }
}
