--TEST--
unset restores fixed typed variables to their initial states
--FILE--
<?php
class UnsetFixedTypeObject
{
}

function makeUnsetArrayValue(): int
{
    return 7;
}

function main(): void
{
    $integer = 42;
    $float = 2.5;
    $boolean = true;
    $string = 'value';
    $array = [1, 2];
    $object = new UnsetFixedTypeObject();
    $dynamic = std::any('value');

    unset($integer, $float, $boolean, $string, $array, $object, $dynamic);

    var_dump($integer, $float, $boolean, $string, $array, $object);
    var_dump(
        isset($integer),
        isset($float),
        isset($boolean),
        isset($string),
        isset($array),
        isset($object),
        isset($dynamic),
    );

    $array[] = 5;
    unset($array);
    $appendResult = ($array[] = makeUnsetArrayValue());
    var_dump($array, $appendResult);
}
?>
--EXPECT--
int(0)
float(0)
bool(false)
string(0) ""
array(0) {
}
NULL
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(false)
bool(false)
array(1) {
  [0]=>
  int(7)
}
int(7)
