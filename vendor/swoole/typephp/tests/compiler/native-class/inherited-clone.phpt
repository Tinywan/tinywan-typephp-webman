--TEST--
Native class: clone resolves an inherited __clone method
--FILE--
<?php

#[Native]
class NativeCloneParent
{
    public int $value = 1;

    public function __clone(): void
    {
        $this->value++;
    }
}

#[Native]
class NativeCloneChild extends NativeCloneParent {}

function main(): void
{
    $first = new NativeCloneChild();
    $second = clone $first;
    var_dump($first->value, $second->value);
}
?>
--EXPECT--
int(1)
int(2)
