--TEST--
Native class: statically resolved instanceof is folded at compile time
--FILE--
<?php

#[Native]
class NativeInstanceofBase
{
}

#[Native]
class NativeInstanceofChild extends NativeInstanceofBase
{
}

#[Native]
class NativeInstanceofOther
{
}

function makeInstanceofChild(): NativeInstanceofChild
{
    echo "made\n";
    return new NativeInstanceofChild();
}

function main(): void
{
    $object = new NativeInstanceofChild();
    var_dump($object instanceof NativeInstanceofChild);
    var_dump($object instanceof NativeInstanceofBase);
    var_dump($object instanceof NativeInstanceofOther);
    var_dump(makeInstanceofChild() instanceof NativeInstanceofChild);
    $object = null;
    var_dump($object instanceof NativeInstanceofChild);
    var_dump($object instanceof NativeInstanceofBase);
}
?>
--EXPECT--
bool(true)
bool(true)
bool(false)
made
bool(true)
bool(false)
bool(false)
