--TEST--
Typed class and interface constants support covariant overrides at runtime
--FILE--
<?php

interface TypedConstantContract
{
    const int|string NUMBER = 1;
    const ?int OPTIONAL = null;
}

trait CompatibleConstantTrait
{
    const int TRAIT_VALUE = 3;
}

class TypedConstantBase
{
    const int|string VALUE = 1;
    const ?int EMPTY_VALUE = null;
}

class TypedConstantChild extends TypedConstantBase implements TypedConstantContract
{
    use CompatibleConstantTrait;

    const int VALUE = 2;
    const int EMPTY_VALUE = 6;
    const int NUMBER = 4;
    const int OPTIONAL = 5;
}

function main(): void
{
    var_dump(TypedConstantBase::VALUE);
    var_dump(TypedConstantBase::EMPTY_VALUE);
    var_dump(TypedConstantChild::VALUE);
    var_dump(TypedConstantChild::EMPTY_VALUE);
    var_dump(TypedConstantChild::NUMBER);
    var_dump(TypedConstantChild::OPTIONAL);
    var_dump(TypedConstantChild::TRAIT_VALUE);
}
?>
--EXPECT--
int(1)
NULL
int(2)
int(6)
int(4)
int(5)
int(3)
