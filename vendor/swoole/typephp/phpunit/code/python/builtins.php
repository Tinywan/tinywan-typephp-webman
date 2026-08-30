<?php

function pythonBuiltins(): void
{
    $list = python\list([1, 2, 3]);
    $dict = Python\dict(['answer' => 42]);
    $tuple = PYTHON\tuple([1, 2]);
    $set = python\set([1, 2]);
    $str = python\str(123);
    $int = python\int('42');
    $object = python\object('value');
    $bytes = python\bytes('value');
    $value = python\len($list);
    $scalar = python\scalar($int)->toInt();

    python\print($dict, $tuple, $set, $str, $int, $object, $bytes, $value, $scalar);
}

function main(): void
{
}
