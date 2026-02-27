<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tool\Core;

use Elabx\ProcessWireMcp\Client\McpClient;
use Elabx\ProcessWireMcp\Client\McpClientException;
use Elabx\ProcessWireMcp\Tool\ProcessWireMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

/**
 * Remote site bridge tools for multi-instance MCP via DDEV
 *
 * Enables cross-site tool execution by spawning subprocess calls
 * to remote MCP servers via `ddev exec`.
 */
class RemoteSiteTools extends ProcessWireMcpTool
{
    public static function getToolInfo(): array
    {
        return [
            'name' => 'remote_site_tools',
            'description' => 'Remote site bridge tools for multi-instance MCP via DDEV',
            'priority' => 200,
        ];
    }

    /**
     * List configured remote sites
     *
     * @return array List of remote site configurations
     */
    #[McpTool(
        name: 'list_remote_sites',
        description: 'List all configured remote ProcessWire sites accessible via DDEV. Returns project names, labels, and paths.'
    )]
    public function listRemoteSites(): array
    {
        $sites = $this->getConfiguredSites();

        if (empty($sites)) {
            return $this->success([
                'count' => 0,
                'sites' => [],
                'message' => 'No remote sites configured. Add sites in the ProcessWireMcp module configuration.',
            ]);
        }

        return $this->success([
            'count' => count($sites),
            'sites' => $sites,
        ]);
    }

    /**
     * List tools available on a remote site
     *
     * @param string $site Remote site project name
     * @return array List of available tools
     */
    #[McpTool(
        name: 'list_remote_tools',
        description: 'List all MCP tools available on a remote ProcessWire site. Use list_remote_sites first to see available sites.'
    )]
    public function listRemoteTools(string $site): array
    {
        try {
            $siteConfig = $this->resolveAndValidateSite($site);
            $client = new McpClient($siteConfig['project'], $siteConfig['pwPath']);
            $tools = $client->listTools();

            return $this->success([
                'site' => $siteConfig['project'],
                'count' => count($tools),
                'tools' => $tools,
            ]);
        } catch (McpClientException $e) {
            return $this->error(
                "Failed to list tools on '{$site}': " . $e->getMessage(),
                $this->mapExceptionCode($e->getCode())
            );
        }
    }

    /**
     * Execute a tool on a remote site
     *
     * @param string $site Remote site project name
     * @param string $tool Tool name to execute
     * @param array $arguments Tool arguments
     * @return array Tool execution result
     */
    #[McpTool(
        name: 'remote_call',
        description: 'Execute an MCP tool on a remote ProcessWire site via DDEV. Use list_remote_sites to see available sites and list_remote_tools to see available tools on a site.'
    )]
    public function remoteCall(
        string $site,
        string $tool,
        #[Schema(type: 'object', description: 'Tool arguments as key-value pairs', additionalProperties: true)]
        array $arguments = []
    ): array {
        try {
            $siteConfig = $this->resolveAndValidateSite($site);
            $client = new McpClient($siteConfig['project'], $siteConfig['pwPath']);
            $result = $client->callTool($tool, $arguments);

            return $this->success([
                'site' => $siteConfig['project'],
                'tool' => $tool,
                'result' => $result,
            ]);
        } catch (McpClientException $e) {
            return $this->error(
                "Failed to call '{$tool}' on '{$site}': " . $e->getMessage(),
                $this->mapExceptionCode($e->getCode())
            );
        }
    }

    /**
     * Parse configured remote sites from module config
     *
     * Format per line: `project-name | Optional Label | /optional/pw/path`
     * Lines starting with # are comments.
     *
     * @return array<array{project: string, label: string, pwPath: string}>
     */
    public function getConfiguredSites(): array
    {
        $config = $this->getRemoteSitesConfig();

        if (empty($config)) {
            return [];
        }

        $sites = [];
        $lines = array_filter(array_map('trim', explode("\n", $config)));

        foreach ($lines as $line) {
            // Skip comments
            if (str_starts_with($line, '#')) {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));

            $project = $parts[0] ?? '';
            if ($project === '') {
                continue;
            }

            // Validate project name format
            if (!preg_match('/^[a-z0-9-]+$/', $project)) {
                continue;
            }

            $sites[] = [
                'project' => $project,
                'label' => $parts[1] ?? $project,
                'pwPath' => $parts[2] ?? '/var/www/html',
            ];
        }

        return $sites;
    }

    /**
     * Resolve a site name to its config and validate it's whitelisted
     *
     * @param string $site Site project name
     * @return array{project: string, label: string, pwPath: string}
     * @throws McpClientException If site is not whitelisted or invalid
     */
    public function resolveAndValidateSite(string $site): array
    {
        // Validate project name format
        if (!preg_match('/^[a-z0-9-]+$/', $site)) {
            throw new McpClientException(
                "Invalid site name '{$site}': only lowercase letters, numbers, and hyphens are allowed",
                McpClientException::INVALID_SITE
            );
        }

        $sites = $this->getConfiguredSites();

        foreach ($sites as $siteConfig) {
            if ($siteConfig['project'] === $site) {
                return $siteConfig;
            }
        }

        throw new McpClientException(
            "Site '{$site}' is not configured. Use list_remote_sites to see available sites.",
            McpClientException::INVALID_SITE
        );
    }

    /**
     * Get the remoteSites config string from the module
     */
    protected function getRemoteSitesConfig(): string
    {
        try {
            $modules = $this->modules();
            $config = $modules->getConfig('ProcessWireMcp');

            return $config['remoteSites'] ?? '';
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Map McpClientException codes to string error codes
     */
    private function mapExceptionCode(int $code): string
    {
        return match ($code) {
            McpClientException::PROCESS_FAILED => 'PROCESS_FAILED',
            McpClientException::HANDSHAKE_FAILED => 'HANDSHAKE_FAILED',
            McpClientException::TIMEOUT => 'TIMEOUT',
            McpClientException::PARSE_ERROR => 'PARSE_ERROR',
            McpClientException::REMOTE_ERROR => 'REMOTE_ERROR',
            McpClientException::INVALID_SITE => 'INVALID_SITE',
            default => 'UNKNOWN_ERROR',
        };
    }
}
