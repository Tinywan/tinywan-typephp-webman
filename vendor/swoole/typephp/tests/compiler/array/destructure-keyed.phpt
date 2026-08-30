--TEST--
array destructuring assignment supports keyed and nested items
--FILE--
<?php

function main(): void
{
    ['id' => $id, 'pair' => [$left, $right]] = [
        'id' => 42,
        'pair' => ['left', 'right'],
    ];

    var_dump($id, $left, $right);
}
?>
--EXPECT--
int(42)
string(4) "left"
string(5) "right"
