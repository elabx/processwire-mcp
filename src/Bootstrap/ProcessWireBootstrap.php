<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Bootstrap;

use ProcessWire\ProcessWire;

/**
 * Bootstraps ProcessWire for CLI usage
 */
class ProcessWireBootstrap
{
    /**
     * Boot ProcessWire from the given path
     *
     * @param string $path Path to ProcessWire installation root
     * @return ProcessWire The ProcessWire instance
     * @throws \RuntimeException If ProcessWire cannot be initialized
     */
    public static function boot(string $path): ProcessWire
    {
        // Validate the path
        $indexPath = rtrim($path, '/') . '/index.php';

        if (!file_exists($indexPath)) {
            throw new \RuntimeException(
                "ProcessWire index.php not found at: {$indexPath}\n" .
                "Make sure --pw-path points to your ProcessWire installation root."
            );
        }

        // Check for site/config.php
        $configPath = rtrim($path, '/') . '/site/config.php';
        if (!file_exists($configPath)) {
            throw new \RuntimeException(
                "ProcessWire config.php not found at: {$configPath}\n" .
                "This does not appear to be a valid ProcessWire installation."
            );
        }

        // Change to ProcessWire directory
        $originalDir = getcwd();
        chdir($path);

        // Set CLI mode
        if (!defined('PROCESSWIRE_CLI')) {
            define('PROCESSWIRE_CLI', true);
        }

        // Prevent ProcessWire from outputting anything
        ob_start();

        try {
            // Include ProcessWire's index.php (creates $wire internally)
            require $indexPath;

            ob_end_clean();

            // index.php doesn't return the instance, so retrieve it
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
