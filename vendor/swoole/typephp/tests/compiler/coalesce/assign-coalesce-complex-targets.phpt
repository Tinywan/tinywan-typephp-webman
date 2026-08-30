--TEST--
null coalescing assignment with object property and array dim targets
--FILE--
<?php

class CoalesceBox
{
    public $value = null;
    public array $items = [];
}

function make_default(string $label): string
{
    echo "default:$label\n";
    return $label;
}

function main(): void
{
    $box = new CoalesceBox();
    $box->value ??= make_default('prop');
    $box->value ??= make_default('prop-skip');
    var_dump($box->value);

    $box->items['name'] ??= make_default('array');
    $box->items['name'] ??= make_default('array-skip');
    var_dump($box->items);
}
?>
--EXPECT--
default:prop
string(4) "prop"
default:array
array(1) {
  ["name"]=>
  string(5) "array"
}
