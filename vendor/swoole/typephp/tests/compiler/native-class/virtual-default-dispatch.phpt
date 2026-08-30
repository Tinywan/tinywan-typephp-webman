--TEST--
Native class: virtual calls use defaults from the dynamically selected implementation
--FILE--
<?php

#[Native]
class NativeDefaultBase
{
    public function value(int $first = 1, int $second = 10): int
    {
        return $first + $second;
    }
}

#[Native]
class NativeDefaultChild extends NativeDefaultBase
{
    public function value(int $first = 2, int $second = 20): int
    {
        return $first + $second;
    }
}

function throughBase(NativeDefaultBase $value): void
{
    var_dump($value->value());
    var_dump($value->value(5));
    var_dump($value->value(first: 6));
    var_dump($value->value(5, 50));
}

function main(): void
{
    throughBase(new NativeDefaultBase());
    throughBase(new NativeDefaultChild());
}
?>
--EXPECT--
int(11)
int(15)
int(16)
int(55)
int(22)
int(25)
int(26)
int(55)
