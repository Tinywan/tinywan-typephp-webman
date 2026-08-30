--TEST--
object link operator
--FILE--
<?php
class StackHolder
{
    public static array $stack = [];

    public static function push(string $item): void
    {
        self::$stack[] = $item;
    }

    public static function pop(): void
    {
        array_pop(self::$stack);
    }

    public static function count(): int
    {
        return count(self::$stack);
    }
}

function main(): int
{
    StackHolder::push('a');
    StackHolder::push('b');
    StackHolder::pop();

    if (StackHolder::count() !== 1) {
        echo "FAIL: expected 1, got " . StackHolder::count() . "\n";
        return 1;
    }

    echo "OK\n";
    return 0;
}
?>
--EXPECT--
OK
