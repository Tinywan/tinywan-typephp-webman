--TEST--
dynamic PHP foreach consumes native TypePHP generator
--FILE--
<?php
function native_values(): iterable
{
    yield 'native-a' => 100;
    yield 'native-b' => 200;
}

function main(): void
{
    require __DIR__ . '/dynamic-generator-interop.inc';

    foreach (dynamic_collect_iterable(native_values()) as $line) {
        echo $line, "\n";
    }
}
?>
--EXPECT--
native-a:100
native-b:200
