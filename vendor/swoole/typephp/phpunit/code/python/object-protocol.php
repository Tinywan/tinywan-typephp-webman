<?php

use Python\sys;
use Python\protocol;

function main(): void
{
    sys\path->append('/tmp');
    $object = protocol\make();
    $object->name = 'value';
    $value = $object->child->method(suffix: '!');
    $integer = $object->toInt();
    $item = $object[0];
    $object[1] = $value;
    $object[0] += python\int(1);
    $object->counter += python\int(1);
    unset($object[2]);
    $exists = isset($object[3]);
    foreach ($object as $key => $entry) {
        echo $key, $entry;
    }
    $result = $object(1, right: 2);
    var_dump($integer);
}
