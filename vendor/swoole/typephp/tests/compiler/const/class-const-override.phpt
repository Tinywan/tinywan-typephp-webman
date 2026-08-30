--TEST--
class const override referencing another constant
--FILE--
<?php

declare(strict_types=1);

abstract class ParentClass
{
    public const A = 'A';
    public const B = 'B';
}

class TestClass extends ParentClass
{
    public const A = ParentClass::B;
    public const B = 'bbb';
}

function main()
{
    var_dump(ParentClass::A, ParentClass::B);
    var_dump(TestClass::A, TestClass::B);
}
?>
--EXPECT--
string(1) "A"
string(1) "B"
string(1) "B"
string(3) "bbb"
