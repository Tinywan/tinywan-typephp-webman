--TEST--
yield from forwards array keys and values
--FILE--
<?php
function gen_from_array(): iterable
{
    yield 'start' => 0;
    yield from ['a' => 1, 'b' => 2];
    yield 'end' => 3;
}

function main(): void
{
    foreach (gen_from_array() as $key => $value) {
        echo $key, ':', $value, "\n";
    }
}
?>
--EXPECT--
start:0
a:1
b:2
end:3
