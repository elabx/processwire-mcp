<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap for ProcessWire MCP tests.
 *
 * Loads the website's Composer autoloader (which includes this package
 * and all its dependencies), then boots ProcessWire in CLI mode.
 *
 * Requires running inside DDEV or the Docker test container:
 *   DDEV:   ddev exec vendor/bin/phpunit --configuration /packages/ProcessWireMcp/phpunit.xml
 *   Docker: docker compose -f docker-compose.test.yml run --rm tests
 */

// Use the website's autoloader (has MCP SDK, ProcessWire, etc.)
$pwPath = getenv('PW_PATH') ?: '/var/www/html';
$autoloader = $pwPath . '/vendor/autoload.php';

if (!file_exists($autoloader)) {
    fwrite(STDERR, "Autoloader not found at {$autoloader}\n");
    fwrite(STDERR, "Tests must run inside DDEV or Docker:\n");
    fwrite(STDERR, "  DDEV:   ddev exec vendor/bin/phpunit --configuration /packages/ProcessWireMcp/phpunit.xml\n");
    fwrite(STDERR, "  Docker: docker compose -f docker-compose.test.yml run --rm tests\n");
    exit(1);
}

require $autoloader;

// Boot ProcessWire
chdir($pwPath);

if (!defined('PROCESSWIRE')) {
    define('PROCESSWIRE', 300);
}

ob_start();
require $pwPath . '/index.php';
ob_end_clean();

$wire = \ProcessWire\ProcessWire::getCurrentInstance();

if (!$wire) {
    fwrite(STDERR, "Failed to bootstrap ProcessWire\n");
    exit(1);
}

// Make the ProcessWire instance available to tests
define('PW_BOOT_COMPLETE', true);
