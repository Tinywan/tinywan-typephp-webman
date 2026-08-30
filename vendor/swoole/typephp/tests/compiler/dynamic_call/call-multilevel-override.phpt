--TEST--
call method overridden in grandchild through base parameter
--FILE--
<?php
use native_types;

class Base
{
    public function value(): string
    {
        return 'base';
    }
}

class Middle extends Base
{
}

class Leaf extends Middle
{
    public function value(): string
    {
        return 'leaf';
    }
}

function readValue(Base $obj): string
{
    $alias = $obj;
    return $alias->value();
}

function main(): int
{
    $r = readValue(new Leaf());
    echo "result: $r\n";
    return $r === 'leaf' ? 0 : 1;
}
?>
--EXPECT--
result: leaf
