<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Server;

use ProcessWire\ProcessWire;
use Elabx\ProcessWireMcp\Tool\ProcessWireMcpTool;
use Elabx\ProcessWireMcp\Resource\ProcessWireMcpResource;
use Psr\Container\ContainerInterface;

/**
 * PSR-11 container that resolves tool/resource classes with ProcessWire injected.
 *
 * Resets ProcessWire's in-memory caches before each tool/resource call so that
 * changes made outside the MCP process (migrations, admin UI, scripts) are
 * visible without restarting the server.
 *
 * Why not re-bootstrap: new ProcessWire() triggers setStatus(ready) which
 * re-includes site/ready.php via include (not include_once). Any bare function
 * declarations in that file cause a "Cannot redeclare" fatal on the second call.
 *
 * Instead we keep the single PW instance and null the three lazy-loaded caches
 * (fields, fieldgroups, templates) — they reference each other so all three
 * must be cleared together. The useLazy=false part is critical: without it,
 * load() fills lazyItems but getAll() never materializes them.
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
        // Reset PW's in-memory caches so each call sees current DB state
        $this->refreshWireState();

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

    /**
     * Force ProcessWire to reload fields, fieldgroups, and templates from DB.
     *
     * PW lazy-loads these into in-memory arrays once and never refreshes.
     * We null the cached array, clear lazy-loading state, and disable lazy
     * mode so that the next init() call loads items directly from the
     * database into the array (not into lazyItems which would require
     * loadAllLazyItems() to materialize).
     *
     * All three (fields, fieldgroups, templates) must be cleared together
     * because they hold cross-references to each other.
     */
    private function refreshWireState(): void
    {
        $targets = [
            'fields' => 'fieldsArray',
            'fieldgroups' => 'fieldgroupsArray',
            'templates' => 'templatesArray',
        ];

        foreach ($targets as $apiVar => $property) {
            $obj = $this->wire->wire($apiVar);
            if ($obj === null) {
                continue;
            }

            $ref = new \ReflectionClass($obj);

            // Null the concrete array cache (e.g. Fields::$fieldsArray)
            if ($ref->hasProperty($property)) {
                $prop = $ref->getProperty($property);
                $prop->setAccessible(true);
                $prop->setValue($obj, null);
            }

            // Clear WireSaveableItems lazy-loading state and disable lazy mode
            // so load() populates the array directly instead of deferred.
            // Without useLazy=false, load() fills lazyItems but getAll()
            // calls loadAllLazyItems() before getWireArray(), so the newly
            // loaded lazy items never get materialized.
            $parentRef = new \ReflectionClass(\ProcessWire\WireSaveableItems::class);
            foreach (['lazyItems' => [], 'lazyNameIndex' => [], 'lazyIdIndex' => [], 'useLazy' => false] as $prop => $val) {
                if ($parentRef->hasProperty($prop)) {
                    $rp = $parentRef->getProperty($prop);
                    $rp->setAccessible(true);
                    $rp->setValue($obj, $val);
                }
            }

            // Trigger reload from DB
            $obj->init();
        }
    }
}
