<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Client;

/**
 * Standalone JSON-RPC over stdio client for MCP servers
 *
 * Spawns a subprocess via `ddev exec`, performs the MCP handshake,
 * sends a single request, and returns the result. Each call is stateless.
 */
class McpClient
{
    private string $ddevProject;
    private string $pwPath;
    private int $timeoutSeconds;

    public function __construct(string $ddevProject, string $pwPath = '/var/www/html', int $timeoutSeconds = 30)
    {
        $this->ddevProject = $ddevProject;
        $this->pwPath = $pwPath;
        $this->timeoutSeconds = $timeoutSeconds;
    }

    /**
     * Call a tool on the remote MCP server
     *
     * @param string $toolName Tool name to call
     * @param array $arguments Tool arguments
     * @return array Decoded result from the tool
     * @throws McpClientException
     */
    public function callTool(string $toolName, array $arguments = []): array
    {
        $pipes = [];
        $process = $this->spawnProcess($pipes);

        try {
            $this->performHandshake($pipes[0], $pipes[1]);

            $request = [
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'tools/call',
                'params' => [
                    'name' => $toolName,
                    'arguments' => (object) $arguments,
                ],
            ];

            $this->sendMessage($pipes[0], $request);
            $response = $this->readResponse($pipes[1]);

            if (isset($response['error'])) {
                throw new McpClientException(
                    'Remote tool error: ' . ($response['error']['message'] ?? 'Unknown error'),
                    McpClientException::REMOTE_ERROR
                );
            }

            // Unwrap result.content[0].text
            $content = $response['result']['content'] ?? [];
            if (!empty($content) && isset($content[0]['text'])) {
                $decoded = json_decode($content[0]['text'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
                return ['text' => $content[0]['text']];
            }

            return $response['result'] ?? [];
        } finally {
            $this->cleanup($process, $pipes);
        }
    }

    /**
     * List tools available on the remote MCP server
     *
     * @return array List of tool definitions
     * @throws McpClientException
     */
    public function listTools(): array
    {
        $pipes = [];
        $process = $this->spawnProcess($pipes);

        try {
            $this->performHandshake($pipes[0], $pipes[1]);

            $request = [
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'tools/list',
            ];

            $this->sendMessage($pipes[0], $request);
            $response = $this->readResponse($pipes[1]);

            if (isset($response['error'])) {
                throw new McpClientException(
                    'Remote error listing tools: ' . ($response['error']['message'] ?? 'Unknown error'),
                    McpClientException::REMOTE_ERROR
                );
            }

            return $response['result']['tools'] ?? [];
        } finally {
            $this->cleanup($process, $pipes);
        }
    }

    /**
     * Spawn the MCP server subprocess via ddev exec
     *
     * @param array &$pipes Pipes array populated by proc_open
     * @return resource Process handle
     * @throws McpClientException
     */
    private function spawnProcess(array &$pipes)
    {
        $project = escapeshellarg($this->ddevProject);
        $pwPath = escapeshellarg($this->pwPath);

        $command = "ddev exec -p {$project} php vendor/bin/pw-mcp-server --pw-path={$pwPath}";

        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'r'], // stdout
            2 => ['pipe', 'w'], // stderr (discard)
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            throw new McpClientException(
                "Failed to spawn MCP server process for project '{$this->ddevProject}'",
                McpClientException::PROCESS_FAILED
            );
        }

        // Set timeout on stdout
        stream_set_timeout($pipes[1], $this->timeoutSeconds);

        return $process;
    }

    /**
     * Perform the MCP initialize/initialized handshake
     *
     * @param resource $stdin Writable pipe to process stdin
     * @param resource $stdout Readable pipe from process stdout
     * @throws McpClientException
     */
    private function performHandshake($stdin, $stdout): void
    {
        // Send initialize request
        $initRequest = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => (object) [],
                'clientInfo' => [
                    'name' => 'processwire-mcp-bridge',
                    'version' => '1.0.0',
                ],
            ],
        ];

        $this->sendMessage($stdin, $initRequest);
        $response = $this->readResponse($stdout);

        if (isset($response['error'])) {
            throw new McpClientException(
                'Handshake failed: ' . ($response['error']['message'] ?? 'Unknown error'),
                McpClientException::HANDSHAKE_FAILED
            );
        }

        // Send initialized notification (no id = notification)
        $this->sendMessage($stdin, [
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ]);
    }

    /**
     * Send a JSON-RPC message to the process stdin
     *
     * @param resource $stdin Writable pipe
     * @param array $message JSON-RPC message
     */
    private function sendMessage($stdin, array $message): void
    {
        fwrite($stdin, json_encode($message) . "\n");
        fflush($stdin);
    }

    /**
     * Read a JSON-RPC response from the process stdout
     *
     * Skips notification messages (those without an 'id' field).
     *
     * @param resource $stdout Readable pipe
     * @return array Decoded JSON-RPC response
     * @throws McpClientException
     */
    private function readResponse($stdout): array
    {
        while (true) {
            $line = fgets($stdout);

            if ($line === false) {
                $meta = stream_get_meta_data($stdout);
                if (!empty($meta['timed_out'])) {
                    throw new McpClientException(
                        'Timeout waiting for response from remote MCP server',
                        McpClientException::TIMEOUT
                    );
                }
                throw new McpClientException(
                    'Remote MCP server closed connection unexpectedly',
                    McpClientException::PROCESS_FAILED
                );
            }

            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new McpClientException(
                    'Failed to parse JSON response: ' . json_last_error_msg(),
                    McpClientException::PARSE_ERROR
                );
            }

            // Skip notifications (messages without an 'id')
            if (!isset($decoded['id'])) {
                continue;
            }

            return $decoded;
        }
    }

    /**
     * Clean up process and pipes
     *
     * @param resource $process Process handle
     * @param array $pipes Pipe handles
     */
    private function cleanup($process, array $pipes): void
    {
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        if (is_resource($process)) {
            proc_terminate($process);
            proc_close($process);
        }
    }
}
