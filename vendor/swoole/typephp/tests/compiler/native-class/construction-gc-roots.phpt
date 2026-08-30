--TEST--
Native class: construction and clone hooks root objects across automatic collection
--FILE--
<?php

#[Native]
class NativeConstructionPressure
{
    public int $value;
}

function createPressure(): void
{
    for ($i = 0; $i < 300000; $i++) {
        $filler = new NativeConstructionPressure();
    }
}

#[Native]
class NativeRootedConstruction
{
    public int $value;

    public function __construct(int $value)
    {
        createPressure();
        $this->value = $value;
    }

    public function __clone(): void
    {
        createPressure();
        $this->value++;
    }
}

function main(): void
{
    $object = new NativeRootedConstruction(41);
    var_dump($object->value);

    $copy = clone $object;
    var_dump($object->value, $copy->value);
}

?>
--EXPECT--
int(41)
int(41)
int(42)
