--TEST--
Python proxies support attributes, methods, items, iteration and callable objects
--SKIPIF--
<?php
if (!extension_loaded('phpy')) {
    die('skip phpy extension is not loaded');
}
?>
--FILE--
<?php

use Python\sys;
use Python\typephp_protocol as protocol;

function scalarValue(PyObject $value): mixed
{
    return python\scalar($value);
}

function mark(string $name, int $value): int
{
    echo $name;
    return $value;
}

function main(): void
{
    sys\path->append(__DIR__ . '/lib');
    $object = protocol\protocol_object();

    var_dump(scalarValue($object->name));
    $object->name = 'changed';
    var_dump(scalarValue($object->greet('hello', suffix: '?')));
    var_dump(scalarValue($object(mark('L', 3), right: mark('R', 4))));
    $args = [5];
    var_dump(scalarValue($object(...$args, right: 6)));

    $values = $object->values;
    var_dump(isset($values[-1]));
    var_dump(isset($values[-4]));
    var_dump(scalarValue($values[-1]));
    $values[-1] = 40;
    unset($values[-2]);
    $values[0] += python\int(5);

    $object->counter = python\int(2);
    $object->counter += 3;
    var_dump(scalarValue($object->counter));

    foreach ($values as $key => $value) {
        echo $key, ':', scalarValue($value), "\n";
    }

    $dict = python\dict(['first' => 1, 'second' => 2]);
    var_dump(isset($dict['missing']));
    $dict['third'] = 3;
    unset($dict['first']);
    foreach ($dict as $key => $value) {
        echo $key, '=', scalarValue($value), "\n";
    }

    unset($object->name);
    try {
        $object->name;
    } catch (PyError $error) {
        echo "attribute deleted\n";
    }

    $object->name = 'not callable';
    try {
        $object->name();
    } catch (PyError $error) {
        echo "attribute is not callable\n";
    }

    try {
        python\int(42)();
    } catch (PyError $error) {
        echo "object is not callable\n";
    }
}
?>
--EXPECT--
string(7) "initial"
string(14) "hello changed?"
LRint(7)
int(11)
bool(true)
bool(false)
int(30)
int(5)
0:15
1:40
bool(false)
second=2
third=3
attribute deleted
attribute is not callable
object is not callable
