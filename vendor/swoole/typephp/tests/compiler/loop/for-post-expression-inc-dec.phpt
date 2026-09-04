--TEST--
for post-expression increment and decrement preserve runtime semantics
--FILE--
<?php

final class LoopCounter
{
    public int $value = 0;
    public static int $staticValue = 10;
}

function ascending(mixed $start): void
{
    for ($i = $start; $i < 3; $i++) {
        echo $i;
    }
    echo ':', $i, "\n";
}

function descending(mixed $start): void
{
    for ($i = $start; $i > 0; $i--) {
        echo $i;
    }
    echo ':', $i, "\n";
}

function main(): void
{
    ascending(0);
    descending(3);

    $object = new LoopCounter();
    $values = [0];
    for (
        $i = 0;
        $i < 3;
        $i++, $object->value++, LoopCounter::$staticValue--, $values[0]++
    ) {
    }

    var_dump($i, $object->value, LoopCounter::$staticValue, $values[0]);
}
?>
--EXPECT--
012:3
321:0
int(3)
int(3)
int(7)
int(3)
