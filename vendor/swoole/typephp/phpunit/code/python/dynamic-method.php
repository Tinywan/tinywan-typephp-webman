<?php

function callDynamicPythonMethod(PyObject $object, string $method): mixed
{
    return $object->$method(1);
}
