--TEST--
foreach list destructuring can write into object properties and array dimensions
--FILE--
<?php

class ForeachListWriteBox
{
    public $name = '';
    public $value = 0;
}

function main(): void
{
    $rows = [
        ['first', 10],
        ['second', 20],
    ];
    $box = new ForeachListWriteBox();
    $out = [];

    foreach ($rows as [$box->name, $out['value']]) {
        $box->value += $out['value'];
        var_dump($box->name, $out['value'], $box->value);
    }
}
?>
--EXPECT--
string(5) "first"
int(10)
int(10)
string(6) "second"
int(20)
int(30)
