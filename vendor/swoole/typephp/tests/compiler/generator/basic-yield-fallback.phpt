--TEST--
generator functions via Fiber iterator
--FILE--
<?php

function gen_values(int $start): iterable
{
    yield 'a' => $start;
    yield 'b' => $start + 1;
}

function main(): void
{
    $gen = gen_values(10);
    var_dump($gen instanceof Iterator);
    foreach ($gen as $key => $value) {
        var_dump($key . ':' . $value);
    }
}
?>
--EXPECT--
bool(true)
string(4) "a:10"
string(4) "b:11"
