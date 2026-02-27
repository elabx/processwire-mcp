<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tests\Tool;

use Elabx\ProcessWireMcp\Client\McpClientException;
use Elabx\ProcessWireMcp\Tests\ProcessWireTestCase;
use Elabx\ProcessWireMcp\Tool\Core\RemoteSiteTools;

class RemoteSiteToolsTest extends ProcessWireTestCase
{
    private RemoteSiteTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new RemoteSiteTools();
        $this->tools->setWire($this->wire());
    }

    public function testGetToolInfo(): void
    {
        $info = RemoteSiteTools::getToolInfo();

        $this->assertEquals('remote_site_tools', $info['name']);
        $this->assertEquals(200, $info['priority']);
    }

    public function testListRemoteSitesReturnsEmptyWhenNoConfig(): void
    {
        $result = $this->tools->listRemoteSites();

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['data']['count']);
        $this->assertEmpty($result['data']['sites']);
    }

    public function testRemoteCallReturnsInvalidSiteForUnknownSite(): void
    {
        $result = $this->tools->remoteCall('nonexistent-site', 'find_pages', ['selector' => 'template=basic-page']);

        $this->assertFalse($result['success']);
        $this->assertEquals('INVALID_SITE', $result['code']);
    }

    public function testListRemoteToolsReturnsInvalidSiteForUnknownSite(): void
    {
        $result = $this->tools->listRemoteTools('nonexistent-site');

        $this->assertFalse($result['success']);
        $this->assertEquals('INVALID_SITE', $result['code']);
    }

    public function testResolveAndValidateSiteRejectsInvalidCharacters(): void
    {
        $this->expectException(McpClientException::class);
        $this->expectExceptionCode(McpClientException::INVALID_SITE);

        $this->tools->resolveAndValidateSite('INVALID_NAME');
    }

    public function testResolveAndValidateSiteRejectsSpecialCharacters(): void
    {
        $this->expectException(McpClientException::class);
        $this->expectExceptionCode(McpClientException::INVALID_SITE);

        $this->tools->resolveAndValidateSite('site; rm -rf /');
    }

    public function testResolveAndValidateSiteRejectsNonWhitelistedSite(): void
    {
        $this->expectException(McpClientException::class);
        $this->expectExceptionCode(McpClientException::INVALID_SITE);

        $this->tools->resolveAndValidateSite('valid-but-unconfigured');
    }

    public function testConfigParsingWithMockedConfig(): void
    {
        $tools = new class extends RemoteSiteTools {
            private string $mockConfig = '';

            public function setMockConfig(string $config): void
            {
                $this->mockConfig = $config;
            }

            protected function getRemoteSitesConfig(): string
            {
                return $this->mockConfig;
            }
        };

        // Empty config
        $tools->setMockConfig('');
        $this->assertEmpty($tools->getConfiguredSites());

        // Single site with defaults
        $tools->setMockConfig('my-project');
        $sites = $tools->getConfiguredSites();
        $this->assertCount(1, $sites);
        $this->assertEquals('my-project', $sites[0]['project']);
        $this->assertEquals('my-project', $sites[0]['label']);
        $this->assertEquals('/var/www/html', $sites[0]['pwPath']);

        // Full format with all fields
        $tools->setMockConfig('my-project | My Project | /custom/path');
        $sites = $tools->getConfiguredSites();
        $this->assertCount(1, $sites);
        $this->assertEquals('my-project', $sites[0]['project']);
        $this->assertEquals('My Project', $sites[0]['label']);
        $this->assertEquals('/custom/path', $sites[0]['pwPath']);
    }

    public function testConfigParsingSkipsComments(): void
    {
        $tools = new class extends RemoteSiteTools {
            private string $mockConfig = '';

            public function setMockConfig(string $config): void
            {
                $this->mockConfig = $config;
            }

            protected function getRemoteSitesConfig(): string
            {
                return $this->mockConfig;
            }
        };

        $tools->setMockConfig("# This is a comment\nmy-project\n# Another comment");
        $sites = $tools->getConfiguredSites();
        $this->assertCount(1, $sites);
        $this->assertEquals('my-project', $sites[0]['project']);
    }

    public function testConfigParsingSkipsInvalidProjectNames(): void
    {
        $tools = new class extends RemoteSiteTools {
            private string $mockConfig = '';

            public function setMockConfig(string $config): void
            {
                $this->mockConfig = $config;
            }

            protected function getRemoteSitesConfig(): string
            {
                return $this->mockConfig;
            }
        };

        $tools->setMockConfig("valid-project\nINVALID_NAME\nhas spaces\nalso-valid");
        $sites = $tools->getConfiguredSites();
        $this->assertCount(2, $sites);
        $this->assertEquals('valid-project', $sites[0]['project']);
        $this->assertEquals('also-valid', $sites[1]['project']);
    }

    public function testConfigParsingSkipsEmptyLines(): void
    {
        $tools = new class extends RemoteSiteTools {
            private string $mockConfig = '';

            public function setMockConfig(string $config): void
            {
                $this->mockConfig = $config;
            }

            protected function getRemoteSitesConfig(): string
            {
                return $this->mockConfig;
            }
        };

        $tools->setMockConfig("project-a\n\n\nproject-b\n  \n");
        $sites = $tools->getConfiguredSites();
        $this->assertCount(2, $sites);
    }

    public function testConfigParsingMultipleSites(): void
    {
        $tools = new class extends RemoteSiteTools {
            private string $mockConfig = '';

            public function setMockConfig(string $config): void
            {
                $this->mockConfig = $config;
            }

            protected function getRemoteSitesConfig(): string
            {
                return $this->mockConfig;
            }
        };

        $config = implode("\n", [
            '# Production sites',
            'site-a | Site A',
            'site-b | Site B | /custom/path',
            '# Staging',
            'site-c',
        ]);

        $tools->setMockConfig($config);
        $sites = $tools->getConfiguredSites();

        $this->assertCount(3, $sites);

        $this->assertEquals('site-a', $sites[0]['project']);
        $this->assertEquals('Site A', $sites[0]['label']);
        $this->assertEquals('/var/www/html', $sites[0]['pwPath']);

        $this->assertEquals('site-b', $sites[1]['project']);
        $this->assertEquals('Site B', $sites[1]['label']);
        $this->assertEquals('/custom/path', $sites[1]['pwPath']);

        $this->assertEquals('site-c', $sites[2]['project']);
        $this->assertEquals('site-c', $sites[2]['label']);
        $this->assertEquals('/var/www/html', $sites[2]['pwPath']);
    }

    public function testResolveAndValidateSiteWithMockedConfig(): void
    {
        $tools = new class extends RemoteSiteTools {
            private string $mockConfig = '';

            public function setMockConfig(string $config): void
            {
                $this->mockConfig = $config;
            }

            protected function getRemoteSitesConfig(): string
            {
                return $this->mockConfig;
            }
        };

        $tools->setMockConfig('my-project | My Project | /custom/path');

        $resolved = $tools->resolveAndValidateSite('my-project');
        $this->assertEquals('my-project', $resolved['project']);
        $this->assertEquals('My Project', $resolved['label']);
        $this->assertEquals('/custom/path', $resolved['pwPath']);
    }
}
