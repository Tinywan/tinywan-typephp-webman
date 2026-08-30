--TEST--
Native class: internal interfaces are compile-time contracts
--FILE--
<?php

#[Native]
class NativeCountableValue implements Countable
{
    public function count(): int
    {
        return 3;
    }
}

function main(): void
{
    $value = new NativeCountableValue();
    var_dump($value->count());
    var_dump(count($value));
    var_dump($value instanceof Countable);
}
?>
--EXPECT--
int(3)
int(3)
bool(true)
