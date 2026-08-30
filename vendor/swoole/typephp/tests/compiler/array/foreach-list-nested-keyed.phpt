--TEST--
foreach destructuring supports nested and keyed items
--FILE--
<?php

function main(): void
{
    $rows = [
        ['id' => 1, 'pair' => ['a', 'b']],
        ['id' => 2, 'pair' => ['c', 'd']],
    ];

    foreach ($rows as ['id' => $id, 'pair' => [$left, $right]]) {
        var_dump($id, $left, $right);
    }
}
?>
--EXPECT--
int(1)
string(1) "a"
string(1) "b"
int(2)
string(1) "c"
string(1) "d"
