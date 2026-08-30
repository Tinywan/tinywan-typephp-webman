--TEST--
generator automatic integer keys track the greatest integer key
--FILE--
<?php
function mixed_keys(): iterable
{
    yield 'name' => 1;
    yield 2;
    yield 5 => 3;
    yield 4;
    yield -2 => 5;
    yield 6;
}

function main(): void
{
    foreach (mixed_keys() as $key => $value) {
        var_dump($key);
    }
}
?>
--EXPECT--
string(4) "name"
int(0)
int(5)
int(6)
int(-2)
int(7)
