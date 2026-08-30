--TEST--
TypePHP supports explicit-visibility final promoted properties with PHP 8.4 libphp
--FILE--
<?php

class FinalPromotedProperty
{
    public function __construct(
        public final string $value,
    ) {
    }
}

#[Native]
class NativeFinalPromotedProperty
{
    public function __construct(
        public final int $value,
    ) {
    }
}

function main(): void
{
    $object = new FinalPromotedProperty('promoted');
    var_dump($object->value);

    $property = new ReflectionProperty(FinalPromotedProperty::class, 'value');
    var_dump(
        $property->isPublic(),
        $property->isPromoted(),
        $property->isFinal(),
    );

    $native = new NativeFinalPromotedProperty(42);
    var_dump($native->value);
}
?>
--EXPECT--
string(8) "promoted"
bool(true)
bool(true)
bool(true)
int(42)
