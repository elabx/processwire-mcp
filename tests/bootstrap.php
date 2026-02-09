<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap for ProcessWire MCP tests.
 *
 * Loads the website's Composer autoloader (which includes this package
 * and all its dependencies), then boots ProcessWire in CLI mode.
 *
 * Requires running inside DDEV: ddev exec vendor/bin/phpunit ...
 */

// Use the website's autoloader (has MCP SDK, ProcessWire, etc.)
$autoloader = '/var/www/html/vendor/autoload.php';

if (!file_exists($autoloader)) {
    fwrite(STDERR, "Autoloader not found at {$autoloader}\n");
    fwrite(STDERR, "Tests must run inside DDEV: ddev exec vendor/bin/phpunit --configuration /packages/ProcessWireMcp/phpunit.xml\n");
    exit(1);
}

require $autoloader;

// Boot ProcessWire
$pwPath = '/var/www/html';
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
