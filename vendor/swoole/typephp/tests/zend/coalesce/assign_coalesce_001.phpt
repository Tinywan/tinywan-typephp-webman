--TEST--
Coalesce assign (??=): Basic behavior
--FILE--
<?php
// Identity function used to track single-evaluation
function id($arg) {
    return $arg;
}

class Test {
    public static $foo;
    public static $bar;
}

function main() {
    // Refcounted values
    $foo = "fo";
    $foo .= "o";
    $bar = "ba";
    $bar .= "r";
    echo "Simple variables:\n";
    $a = 123;
    $a ??= 456;
    var_dump($a);

    $b = null;
    $b ??= $foo;
    var_dump($b);

    $c = $foo;
    $c ??= $bar;
    var_dump($c);

    $d ??= $foo;
    var_dump($c);

    echo "\nArrays:\n";
    $ary = [];
    $ary["foo"] ??= 123;
    $ary[$foo] ??= $bar;
    $ary[$bar] ??= $foo;
    var_dump($ary);

    echo "\nArrays (identity):\n";
    $ary = [];
    $ary[id($foo)] ??= 123;
    $ary[id($foo)] ??= $bar;
    $ary[id($bar)] ??= $foo;
    var_dump($ary);

    echo "\nObjects:\n";
    $obj = new stdClass;
    $obj->foo ??= 123;
    $obj->$foo ??= $bar;
    $obj->$bar ??= $foo;
    var_dump($obj);

    $obj = new stdClass;
    $obj->{id($foo)} ??= 123;
    $obj->{id($foo)} ??= $bar;
    $obj->{id($bar)} ??= $foo;
    var_dump($obj);

    echo "\nStatic props:\n";
    Test::$foo ??= 123;
    Test::$$foo ??= $bar;
    Test::$$bar ??= $foo;
    var_dump(Test::$foo, Test::$bar);

    Test::$foo = null;
    Test::$bar = null;
    Test::${id($foo)} ??= 123;
    Test::${id($foo)} ??= $bar;
    Test::${id($bar)} ??= $foo;
    var_dump(Test::$foo, Test::$bar);
}
?>
--EXPECT--
Simple variables:
int(123)
string(3) "foo"
string(3) "foo"
string(3) "foo"

Arrays:
array(2) {
  ["foo"]=>
  int(123)
  ["bar"]=>
  string(3) "foo"
}

Arrays (identity):
array(2) {
  ["foo"]=>
  int(123)
  ["bar"]=>
  string(3) "foo"
}

Objects:
object(stdClass)#1 (2) {
  ["foo"]=>
  int(123)
  ["bar"]=>
  string(3) "foo"
}
object(stdClass)#2 (2) {
  ["foo"]=>
  int(123)
  ["bar"]=>
  string(3) "foo"
}

Static props:
int(123)
string(3) "foo"
int(123)
string(3) "foo"
