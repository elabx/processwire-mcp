<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Server;

use ProcessWire\ProcessWire;
use Elabx\ProcessWireMcp\Bootstrap\ProcessWireBootstrap;
use Elabx\ProcessWireMcp\Tool\ProcessWireMcpTool;
use Elabx\ProcessWireMcp\Resource\ProcessWireMcpResource;
use Psr\Container\ContainerInterface;

/**
 * PSR-11 container that resolves tool/resource classes with ProcessWire injected.
 *
 * Re-bootstraps ProcessWire on every tool/resource call so each request sees
 * current DB state (new fields, templates, pages added by migrations, etc.),
 * following PHP's share-nothing principle.
 */
class WireAwareContainer implements ContainerInterface
{
    private ProcessWire $wire;
    private string $pwPath;
    private array $instances = [];

    public function __construct(ProcessWire $wire)
    {
        $this->wire = $wire;
        $this->pwPath = rtrim($wire->wire('config')->paths->root, '/');
    }

    public function get(string $id): mixed
    {
        // Re-bootstrap ProcessWire so each call sees current DB state
        $this->wire = ProcessWireBootstrap::boot($this->pwPath);

        if (!isset($this->instances[$id])) {
            if (!class_exists($id)) {
                throw new class("Class not found: {$id}") extends \Exception implements \Psr\Container\NotFoundExceptionInterface {};
            }

            $instance = new $id();
            $this->instances[$id] = $instance;
        }

        // Always inject the fresh ProcessWire instance
        $instance = $this->instances[$id];
        if ($instance instanceof ProcessWireMcpTool) {
            $instance->setWire($this->wire);
        } elseif ($instance instanceof ProcessWireMcpResource) {
            $instance->setWire($this->wire);
        }

        return $instance;
    }

    public function has(string $id): bool
    {
        return class_exists($id);
    }
}
