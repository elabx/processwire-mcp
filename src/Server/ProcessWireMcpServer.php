<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Server;

use ProcessWire\ProcessWire;
use Elabx\ProcessWireMcp\Tool\ProcessWireMcpTool;
use Elabx\ProcessWireMcp\Resource\ProcessWireMcpResource;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Server;
use Mcp\Server\Transport\StdioTransport;
use Psr\Container\ContainerInterface;

/**
 * ProcessWire MCP Server
 *
 * Main server class that initializes the MCP SDK and registers
 * all ProcessWire tools and resources via attribute discovery.
 */
class ProcessWireMcpServer
{
    private ProcessWire $wire;

    public function __construct(ProcessWire $wire)
    {
        $this->wire = $wire;
    }

    /**
     * Run the MCP server
     */
    public function run(): void
    {
        $packageRoot = dirname(__DIR__, 2);

        $server = Server::builder()
            ->setServerInfo('processwire-mcp', '1.0.0', 'MCP Server for ProcessWire CMS')
            ->setProtocolVersion(ProtocolVersion::V2024_11_05)
            ->setContainer($this->createContainer())
            ->setDiscovery(
                basePath: $packageRoot,
                scanDirs: ['src'],
                excludeDirs: ['Bootstrap', 'Server']
            )
            ->build();

        $transport = new StdioTransport();
        $server->run($transport);
    }

    /**
     * Create a PSR-11 container that injects ProcessWire into tool/resource instances
     */
    private function createContainer(): ContainerInterface
    {
        return new WireAwareContainer($this->wire);
    }
}
