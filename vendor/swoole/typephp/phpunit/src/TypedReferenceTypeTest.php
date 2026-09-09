<?php

use PHPUnit\Framework\TestCase;
use TypePhp\Type;

final class TypedReferenceTypeTest extends TestCase
{
    public function testValueAndReferenceTypeMappingsAreSymmetric(): void
    {
        $types = [
            Type::INT => Type::INT_REF,
            Type::STR => Type::STR_REF,
            Type::FLOAT => Type::FLOAT_REF,
            Type::BOOL => Type::BOOL_REF,
            Type::ARRAY => Type::ARRAY_REF,
        ];

        foreach ($types as $valueType => $referenceType) {
            self::assertSame($referenceType, Type::getReferenceType($valueType));
            self::assertSame($valueType, Type::getReferencedType($referenceType));
            self::assertTrue(Type::isTypedRefType($referenceType));
            self::assertTrue(Type::isAnyRefType($referenceType));
        }
    }

    public function testDynamicReferenceRemainsDistinctFromTypedReferences(): void
    {
        self::assertTrue(Type::isAnyRefType(Type::REF));
        self::assertFalse(Type::isTypedRefType(Type::REF));
        self::assertNull(Type::getReferenceType(Type::VAR));
        self::assertSame(Type::REF, Type::getReferencedType(Type::REF));
    }
}
