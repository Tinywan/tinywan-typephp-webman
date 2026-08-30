--TEST--
native foreach consumes dynamic PHP generator
--FILE--
<?php
function main(): void
{
    require __DIR__ . '/dynamic-generator-interop.inc';

    foreach (dynamic_yield_values() as $key => $value) {
        echo $key, ':', $value, "\n";
    }

    echo "-- yield from --\n";
    foreach (dynamic_yield_from_values() as $key => $value) {
        echo $key, ':', $value, "\n";
    }
}
?>
--EXPECT--
dyn-a:11
dyn-b:22
-- yield from --
dyn-start:0
dyn-a:11
dyn-b:22
dyn-end:33
