<?php

use PHPUnit\Framework\TestCase;
use TypePhp\Type;

final class FixedTypeDefaultValueTest extends TestCase
{
    public function testFixedValueTypesExposeTheirInitialStateExpressions(): void
    {
        self::assertSame('0', Type::getDefaultValueExpression(Type::INT));
        self::assertSame('0.0', Type::getDefaultValueExpression(Type::FLOAT));
        self::assertSame('false', Type::getDefaultValueExpression(Type::BOOL));
        self::assertSame('php::Str()', Type::getDefaultValueExpression(Type::STR));
        self::assertSame('php::Array{}', Type::getDefaultValueExpression(Type::ARRAY));
    }

    public function testObjectAndDynamicStorageHaveSeparateEmptyStateRules(): void
    {
        self::assertNull(Type::getDefaultValueExpression(Type::OBJECT));
        self::assertNull(Type::getDefaultValueExpression(Type::VAR));
        self::assertNull(Type::getDefaultValueExpression(Type::REF));
    }
}
