<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Client;

/**
 * Exception for MCP client errors
 */
class McpClientException extends \RuntimeException
{
    public const PROCESS_FAILED = 1;
    public const HANDSHAKE_FAILED = 2;
    public const TIMEOUT = 3;
    public const PARSE_ERROR = 4;
    public const REMOTE_ERROR = 5;
    public const INVALID_SITE = 6;
}
