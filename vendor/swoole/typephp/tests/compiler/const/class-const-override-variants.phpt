--TEST--
class const override variants (self::class, parent::class, references, multi-level)
--FILE--
<?php

declare(strict_types=1);

class Base
{
    public const NAME = 'Base';
    public const GREETING = 'hello';
    public const VALUE = 42;
}

class Other
{
    public const TAG = 'other';
}

class Mid extends Base
{
    public const NAME = Base::GREETING;       // 'hello'
    public const SELF_NAME = self::class;     // 'Mid'
    public const PARENT_NAME = parent::class; // 'Base'
    public const CROSS = Other::TAG;          // 'other'
}

class Leaf extends Mid
{
    public const VALUE = Mid::NAME;           // 'hello' (overrides Base::VALUE int with a string)
    public const LEAF_NAME = self::class;     // 'Leaf'
    public const GREET = Base::GREETING;      // 'hello'
}

function main()
{
    var_dump(Mid::NAME, Mid::SELF_NAME, Mid::PARENT_NAME, Mid::CROSS);
    var_dump(Leaf::VALUE, Leaf::LEAF_NAME, Leaf::GREET);
    var_dump(Base::VALUE, Leaf::VALUE);
}
?>
--EXPECT--
string(5) "hello"
string(3) "Mid"
string(4) "Base"
string(5) "other"
string(5) "hello"
string(4) "Leaf"
string(5) "hello"
int(42)
string(5) "hello"
