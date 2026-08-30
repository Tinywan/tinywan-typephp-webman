--TEST--
keyed destructuring writes to object properties and array dimensions
--FILE--
<?php

class DestructureKeyedBox
{
    public string $name = '';
}

function main(): void
{
    $box = new DestructureKeyedBox();
    $out = [];

    ['name' => $box->name, 'value' => $out['value']] = [
        'name' => 'alpha',
        'value' => 123,
    ];

    var_dump($box->name, $out['value']);
}
?>
--EXPECT--
string(5) "alpha"
int(123)
