<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Resource;

use ProcessWire\ProcessWire;
use ProcessWire\Pages;
use ProcessWire\Templates;
use ProcessWire\Fields;
use ProcessWire\Users;

/**
 * Base class for all ProcessWire MCP resources
 *
 * Third-party ProcessWire modules can extend this class to register
 * custom MCP resources. Resources provide read-only access to data.
 *
 * Example:
 * ```php
 * class MyCustomResources extends ProcessWireMcpResource
 * {
 *     public static function getResourceInfo(): array
 *     {
 *         return [
 *             'name' => 'my_custom_resources',
 *             'description' => 'Custom resources for my module',
 *         ];
 *     }
 *
 *     // Methods with #[McpResource] attributes...
 * }
 * ```
 */
abstract class ProcessWireMcpResource
{
    protected ProcessWire $wire;

    /**
     * Get resource metadata for registration
     *
     * Override this in subclasses to provide resource information.
     *
     * @return array{name: string, description: string, priority?: int}
     */
    public static function getResourceInfo(): array
    {
        return [
            'name' => '',
            'description' => '',
            'priority' => 100,
        ];
    }

    /**
     * Set the ProcessWire instance
     */
    public function setWire(ProcessWire $wire): void
    {
        $this->wire = $wire;
    }

    /**
     * Get the ProcessWire instance
     */
    protected function wire(): ProcessWire
    {
        return $this->wire;
    }

    /**
     * Get the Pages API
     */
    protected function pages(): Pages
    {
        return $this->wire->wire('pages');
    }

    /**
     * Get the Templates API
     */
    protected function templates(): Templates
    {
        return $this->wire->wire('templates');
    }

    /**
     * Get the Fields API
     */
    protected function fields(): Fields
    {
        return $this->wire->wire('fields');
    }

    /**
     * Get the Users API
     */
    protected function users(): Users
    {
        return $this->wire->wire('users');
    }

    /**
     * Get any wire service by name
     */
    protected function get(string $name): mixed
    {
        return $this->wire->wire($name);
    }
}
