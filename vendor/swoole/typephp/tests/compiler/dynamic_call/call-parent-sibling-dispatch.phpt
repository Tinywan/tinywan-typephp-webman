--TEST--
call overridden method on sibling subclasses through parent parameter
--FILE--
<?php
use native_types;

class Base
{
    public function name(): string
    {
        return 'base';
    }
}

class Left extends Base
{
    public function name(): string
    {
        return 'left';
    }
}

class Right extends Base
{
    public function name(): string
    {
        return 'right';
    }
}

function readName(Base $obj): string
{
    $alias = $obj;
    return $alias->name();
}

function main(): int
{
    $left = readName(new Left());
    $right = readName(new Right());
    echo "left: $left\n";
    echo "right: $right\n";
    return $left === 'left' && $right === 'right' ? 0 : 1;
}
?>
--EXPECT--
left: left
right: right
