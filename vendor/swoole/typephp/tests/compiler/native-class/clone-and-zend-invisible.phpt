--TEST--
Native class: clone is native and the class remains invisible to ZendVM
--FILE--
<?php

#[Native]
class NativeCloneValue
{
    public int $value = 1;

    public function __clone(): void
    {
        $this->value++;
    }
}

function main(): void
{
    $first = new NativeCloneValue();
    $second = clone $first;
    var_dump($first->value, $second->value);
    var_dump(class_exists('NativeCloneValue', false));
}

?>
--EXPECT--
int(1)
int(2)
bool(false)
