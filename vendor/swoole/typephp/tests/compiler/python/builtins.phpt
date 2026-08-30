--TEST--
Python builtins and constructors use phpy objects
--SKIPIF--
<?php
if (!extension_loaded('phpy')) {
    die('skip phpy extension is not loaded');
}
?>
--FILE--
<?php

function main(): void
{
    $list = python\list([1, 2, 3]);
    $dict = Python\dict(['answer' => 42]);
    $tuple = PYTHON\tuple([1, 2]);
    $set = python\set([1, 2]);
    $str = python\str(123);
    $int = python\int('42');
    $object = python\object('value');
    $bytes = python\bytes('value');

    var_dump(get_class($list));
    var_dump(get_class($dict));
    var_dump(get_class($tuple));
    var_dump(get_class($set));
    var_dump(get_class($str));
    var_dump(get_class($int));
    var_dump(get_class($object));
    var_dump(get_class($bytes));
    var_dump(get_class(python\len($list)));
    var_dump(get_class(python\bool(0)));
    var_dump(python\scalar($int)->toInt());

    // The constructor syntax is deliberately phpy's PHP-array conversion,
    // not CPython's dict(iterable-of-pairs) constructor.
    var_dump(python\scalar($dict['answer'])->toInt());
    python\print('hello from python');
}
?>
--EXPECT--
string(6) "PyList"
string(6) "PyDict"
string(7) "PyTuple"
string(5) "PySet"
string(5) "PyStr"
string(8) "PyObject"
string(8) "PyObject"
string(8) "PyObject"
string(8) "PyObject"
string(8) "PyObject"
int(42)
int(42)
hello from python
