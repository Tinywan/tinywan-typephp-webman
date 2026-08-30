--TEST--
foreach iterator destructuring supports nested and keyed items
--FILE--
<?php

function main(): void
{
    $rows = new ArrayObject([
        ['id' => 10, 'pair' => ['x', 'y']],
        ['id' => 20, 'pair' => ['m', 'n']],
    ]);

    foreach ($rows as ['id' => $id, 'pair' => [$left, $right]]) {
        var_dump($id, $left, $right);
    }
}
?>
--EXPECT--
int(10)
string(1) "x"
string(1) "y"
int(20)
string(1) "m"
string(1) "n"
