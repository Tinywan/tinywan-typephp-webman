--TEST--
Reference-returning calls are copied by value when used as call arguments or array elements
--FILE--
<?php

function main()
{
    $v1 = &test1();
    var_dump($v1);
    $v2 = &test1();
    var_dump($v2);
    var_dump($v1, $v2);
    $v1 = 0;
    var_dump($v1, $v2);
    var_dump(test1(), test2());
    var_dump([test1(), test2()]);
    var_dump(['first' => test1(), test2()]);
    var_dump(value_order('arg-left'), ref_order('arg-ref'));
    var_dump([value_order('array-left'), ref_order('array-ref')]);
}

function &test1()
{
    $callback = 'test2';
    return $callback();
}

function &test2()
{
    static $value = 0;
    ++$value;
    return $value;
}

function value_order(string $label): string
{
    echo "$label\n";
    return $label;
}

function &ref_order(string $label)
{
    static $value = 42;
    echo "$label\n";
    return $value;
}
?>
--EXPECT--
int(1)
int(2)
int(2)
int(2)
int(0)
int(0)
int(1)
int(2)
array(2) {
  [0]=>
  int(3)
  [1]=>
  int(4)
}
array(2) {
  ["first"]=>
  int(5)
  [0]=>
  int(6)
}
arg-left
arg-ref
string(8) "arg-left"
int(42)
array-left
array-ref
array(2) {
  [0]=>
  string(10) "array-left"
  [1]=>
  int(42)
}
