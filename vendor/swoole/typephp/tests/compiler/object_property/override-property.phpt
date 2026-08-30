--TEST--
TypePHP Override attribute validates and is consumed from properties
--FILE--
<?php

class OverridePropertyRuntimeParent
{
    public string $value = 'parent';
}

class OverridePropertyRuntimeChild extends OverridePropertyRuntimeParent
{
    #[Override]
    public string $value = 'child';
}

function main(): void
{
    $object = new OverridePropertyRuntimeChild();
    var_dump($object->value);

    $property = new ReflectionProperty(OverridePropertyRuntimeChild::class, 'value');
    var_dump($property->getAttributes(\Override::class));
}
?>
--EXPECT--
string(5) "child"
array(0) {
}
