--TEST--
Static method calls (self / static / class name / parent) with by-reference args
--FILE--
<?php

class P
{
    public static function test(?string &$value): void
    {
        $value = 'test';
    }
}

class TestClass extends P
{
    public static function abc()
    {
        self::test($v1);
        var_dump($v1);
        static::test($v2);
        var_dump($v2);
        TestClass::test($v3);
        var_dump($v3);
        parent::test($v4);
        var_dump($v4);
        static::test(value: $v5);
        var_dump($v5);
        parent::test(value: $v6);
        var_dump($v6);
    }
}

function main()
{
    TestClass::abc();
}
?>
--EXPECT--
string(4) "test"
string(4) "test"
string(4) "test"
string(4) "test"
string(4) "test"
string(4) "test"
