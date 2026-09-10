<?php

use PHPUnit\Framework\TestCase;

final class StdOrderedMapPolyfillTest extends TestCase
{
    public function testUsesCamelCaseFactoryName(): void
    {
        self::assertSame([], std::orderedMap(Type::String, Type::Int));
        self::assertTrue(method_exists(std::class, 'orderedMap'));
        self::assertFalse(method_exists(std::class, 'ordered_map'));
    }
}
