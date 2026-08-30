--TEST--
Native class: cloning through a base pointer preserves the dynamic subclass
--FILE--
<?php

#[Native]
class NativePolymorphicCloneBase
{
    public int $baseValue = 1;

    public function describe(): string
    {
        return 'base:' . $this->baseValue;
    }
}

#[Native]
class NativePolymorphicCloneChild extends NativePolymorphicCloneBase
{
    public int $childValue = 10;

    public function __clone(): void
    {
        $this->baseValue++;
        $this->childValue++;
    }

    public function describe(): string
    {
        return 'child:' . $this->baseValue . ':' . $this->childValue;
    }
}

function cloneNativeBase(NativePolymorphicCloneBase $value): NativePolymorphicCloneBase
{
    return clone $value;
}

function main(): void
{
    $source = new NativePolymorphicCloneChild();
    $copy = cloneNativeBase($source);
    echo $source->describe(), "\n";
    echo $copy->describe(), "\n";
}
?>
--EXPECT--
child:1:10
child:2:11
