--TEST--
ArrayDef checks dynamic compound and high-precision value types
--FILE--
<?php

class ArrayDefCompoundBox
{
    #[ArrayDef(Type::Array)]
    public array $arrays = [];

    #[ArrayDef(Type::Object)]
    public array $objects = [];

    #[ArrayDef(Type::BigInt)]
    public array $bigInts = [];

    #[ArrayDef(Type::BigFloat)]
    public array $bigFloats = [];

    #[ArrayDef(Type::Decimal)]
    public array $decimals = [];
}

function putCompound(ArrayDefCompoundBox $box, int $slot, any $value): void
{
    if ($slot === 0) {
        $box->arrays[] = $value;
    } elseif ($slot === 1) {
        $box->objects[] = $value;
    } elseif ($slot === 2) {
        $box->bigInts[] = $value;
    } elseif ($slot === 3) {
        $box->bigFloats[] = $value;
    } else {
        $box->decimals[] = $value;
    }
}

function main(): void
{
    $box = new ArrayDefCompoundBox();
    putCompound($box, 0, [1, 2]);
    putCompound($box, 1, new stdClass());
    putCompound($box, 2, std::bigInt(3));
    putCompound($box, 3, std::bigFloat('4'));
    putCompound($box, 4, std::decimal('5'));

    var_dump($box->arrays[0]);
    var_dump($box->objects[0] instanceof stdClass);
    echo std::bigInt($box->bigInts[0])->toString(), "\n";
    echo std::bigFloat($box->bigFloats[0])->toString(), "\n";
    echo std::decimal($box->decimals[0])->toString(), "\n";

    try {
        putCompound($box, 0, 'not-array');
    } catch (TypeError $error) {
        echo "array checked\n";
    }
    try {
        putCompound($box, 1, []);
    } catch (TypeError $error) {
        echo "object checked\n";
    }
    try {
        putCompound($box, 2, std::decimal('6'));
    } catch (TypeError $error) {
        echo "BigInt checked\n";
    }
}
?>
--EXPECT--
array(2) {
  [0]=>
  int(1)
  [1]=>
  int(2)
}
bool(true)
3
4
5
array checked
object checked
BigInt checked
