--TEST--
Trait constants can merge another trait constant with array unpacking
--FILE--
<?php
trait TraitConstantArraySpread
{
    private const BASE = [
        'list' => 'PyList',
        'dict' => 'PyDict',
    ];

    public const MERGED = [
        ...self::BASE,
        'int' => 'PyObject',
    ];
}

class TraitConstantArraySpreadUser
{
    use TraitConstantArraySpread;
}

function main(): void
{
    var_dump(TraitConstantArraySpreadUser::MERGED);
}
?>
--EXPECT--
array(3) {
  ["list"]=>
  string(6) "PyList"
  ["dict"]=>
  string(6) "PyDict"
  ["int"]=>
  string(8) "PyObject"
}
