--TEST--
Native class: declarations are emitted in inheritance order
--FILE--
<?php

#[Native]
class NativeOrderChild extends NativeOrderParent
{
    public int $child = 2;
}

#[Native]
class NativeOrderParent
{
    public int $parent = 1;
}

function main(): void
{
    $value = new NativeOrderChild();
    echo $value->parent, ':', $value->child, PHP_EOL;
}
?>
--EXPECT--
1:2
