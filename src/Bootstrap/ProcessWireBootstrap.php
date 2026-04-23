<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Bootstrap;

use ProcessWire\ProcessWire;

/**
 * Bootstraps ProcessWire for CLI usage.
 *
 * ProcessWire is booted once via index.php. Re-bootstrapping with
 * new ProcessWire() is not safe because PW re-includes site/ready.php
 * (via include, not include_once), causing fatal "Cannot redeclare
 * function" errors for any bare function declarations in that file.
 *
 * Instead, the single instance is kept alive and its in-memory caches
 * are reset before each MCP call (see WireAwareContainer).
 */
class ProcessWireBootstrap
{
    /**
     * Boot ProcessWire from the given path.
     *
     * @param string $path Path to ProcessWire installation root
     * @return ProcessWire The ProcessWire instance
     * @throws \RuntimeException If ProcessWire cannot be initialized
     */
    public static function boot(string $path): ProcessWire
    {
        $path = rtrim($path, '/');
        $indexPath = $path . '/index.php';

        if (!file_exists($indexPath)) {
            throw new \RuntimeException(
                "ProcessWire index.php not found at: {$indexPath}\n" .
                "Make sure --pw-path points to your ProcessWire installation root."
            );
        }

        $configPath = $path . '/site/config.php';
        if (!file_exists($configPath)) {
            throw new \RuntimeException(
                "ProcessWire config.php not found at: {$configPath}\n" .
                "This does not appear to be a valid ProcessWire installation."
            );
        }

        $originalDir = getcwd();
        chdir($path);

        if (!defined('PROCESSWIRE_CLI')) {
            define('PROCESSWIRE_CLI', true);
        }

        // Buffer output to suppress deprecation notices / stray output
        ob_start();

        try {
            require $indexPath;

            ob_end_clean();

            $wire = ProcessWire::getCurrentInstance();

            if (!$wire instanceof ProcessWire) {
                throw new \RuntimeException(
                    "Failed to initialize ProcessWire. " .
                    "Could not get ProcessWire instance after loading index.php."
                );
            }

            return $wire;

        } catch (\Throwable $e) {
            ob_end_clean();
            chdir($originalDir);
            throw new \RuntimeException(
                "Failed to bootstrap ProcessWire: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
