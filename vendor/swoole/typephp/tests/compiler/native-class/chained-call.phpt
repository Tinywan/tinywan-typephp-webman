--TEST--
Native class: method calls accept native object expressions as receivers
--FILE--
<?php

#[Native]
class NativeChainValue
{
    public int $value = 42;

    public function getValue(): int
    {
        return $this->value;
    }
}

function makeNativeChainValue(): NativeChainValue
{
    return new NativeChainValue();
}

function main(): void
{
    echo (new NativeChainValue())->getValue(), PHP_EOL;
    echo makeNativeChainValue()->getValue(), PHP_EOL;
    echo makeNativeChainValue()->value, PHP_EOL;
}
?>
--EXPECT--
42
42
42
