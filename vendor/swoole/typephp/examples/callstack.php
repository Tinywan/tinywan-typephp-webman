<?php


class A
{
    static function foo()
    {
        echo "foo 中的 static::class = " . static::class . "\n";
    }

    static function bar()
    {
//        echo "bar 中的 self::class = " . self::class . "\n";
        self::foo(); // 这里使用 self 调用
    }

    static function baz()
    {
        echo "baz 中的 self::class = " . self::class . "\n";
    }
}

class B extends A
{
    // 没有重写任何方法
}

function main()
{
//    echo "=== 调用 A::bar() ===\n";
//    A::bar();

    echo "\n=== 调用 B::bar() ===\n";
    B::bar();

//    echo "\n=== 调用 A::baz() 和 B::baz() ===\n";
//    A::baz();
//    B::baz();;
}
