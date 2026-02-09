<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tests;

use PHPUnit\Framework\TestCase;
use ProcessWire\ProcessWire;

/**
 * Base test case with ProcessWire access.
 */
abstract class ProcessWireTestCase extends TestCase
{
    protected static ProcessWire $wire;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::$wire = ProcessWire::getCurrentInstance();
    }

    protected function wire(): ProcessWire
    {
        return static::$wire;
    }
}
