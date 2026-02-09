<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Server;

use ProcessWire\ProcessWire;
use Elabx\ProcessWireMcp\Tool\ProcessWireMcpTool;
use Elabx\ProcessWireMcp\Resource\ProcessWireMcpResource;
use Psr\Container\ContainerInterface;

/**
 * PSR-11 container that resolves tool/resource classes with ProcessWire injected
 */
class WireAwareContainer implements ContainerInterface
{
    private ProcessWire $wire;
    private array $instances = [];

    public function __construct(ProcessWire $wire)
    {
        $this->wire = $wire;
    }

    public function get(string $id): mixed
    {
        if (!isset($this->instances[$id])) {
            if (!class_exists($id)) {
                throw new class("Class not found: {$id}") extends \Exception implements \Psr\Container\NotFoundExceptionInterface {};
            }

            $instance = new $id();

            // Inject ProcessWire if it's one of our base classes
            if ($instance instanceof ProcessWireMcpTool) {
                $instance->setWire($this->wire);
            } elseif ($instance instanceof ProcessWireMcpResource) {
                $instance->setWire($this->wire);
            }

            $this->instances[$id] = $instance;
        }

        return $this->instances[$id];
    }

    public function has(string $id): bool
    {
        return class_exists($id);
    }
}
