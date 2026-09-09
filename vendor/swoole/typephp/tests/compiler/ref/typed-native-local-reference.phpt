--TEST--
Native typed local references preserve alias and value-copy semantics
--FILE--
<?php

function increment(int &$value): void
{
    $value++;
}

function appendSuffix(string &$value): void
{
    $value .= ':changed';
}

function halve(float &$value): void
{
    $value /= 2.0;
}

function toggle(bool &$value): void
{
    $value = !$value;
}

function appendNumber(array &$value): void
{
    $value[] = 3;
}

function main(): void
{
    $a = 100;
    $b =& $a;
    $c = $b;
    $d =& $b;
    $b = 101;
    $d = 102;
    $c = 200;
    increment($d);
    var_dump($a, $b, $c, $d);

    $string = 'value';
    $stringRef =& $string;
    $stringCopy = $stringRef;
    appendSuffix($stringRef);
    var_dump($string, $stringRef, $stringCopy);

    $float = 8.0;
    $floatRef =& $float;
    halve($floatRef);
    var_dump($float, $floatRef);

    $bool = false;
    $boolRef =& $bool;
    toggle($boolRef);
    var_dump($bool, $boolRef);

    $array = [1, 2];
    $arrayRef =& $array;
    $arrayCopy = $arrayRef;
    appendNumber($arrayRef);
    $arrayCopy[] = 4;
    var_dump($array, $arrayRef, $arrayCopy);
}

?>
--EXPECT--
int(103)
int(103)
int(200)
int(103)
string(13) "value:changed"
string(13) "value:changed"
string(5) "value"
float(4)
float(4)
bool(true)
bool(true)
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
}
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
}
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(4)
}
