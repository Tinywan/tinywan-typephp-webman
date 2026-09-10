<?php

use PHPUnit\Framework\TestCase;

class StdObjectPolyfillBase
{
}

final class StdObjectPolyfillChild extends StdObjectPolyfillBase
{
}

final class StdObjectPolyfillTest extends TestCase
{
    public function testReturnsAnObjectOfTheRequestedClass(): void
    {
        $object = new StdObjectPolyfillBase();

        self::assertSame($object, std::object($object, StdObjectPolyfillBase::class));

        $child = new StdObjectPolyfillChild();
        self::assertSame($child, std::object($child, StdObjectPolyfillBase::class));
    }

    public function testRejectsANonObjectValue(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('must be an object, int given');

        std::object(42, StdObjectPolyfillBase::class);
    }

    public function testRejectsAnUnrelatedObject(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('must be an instance of StdObjectPolyfillBase, stdClass given');

        std::object(new stdClass(), StdObjectPolyfillBase::class);
    }
}
