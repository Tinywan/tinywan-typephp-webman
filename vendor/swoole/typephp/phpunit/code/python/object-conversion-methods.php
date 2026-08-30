<?php

function main(): void
{
    $list = python\list([1, 2, 3]);
    $array = $list->toArray();
    $value = convertPythonValue($list);
    $integer = python\int(42)->toValue()->toInt();
    var_dump($array, $value, $integer);
}

function convertPythonValue(PyObject $value): mixed
{
    return $value->toValue();
}
