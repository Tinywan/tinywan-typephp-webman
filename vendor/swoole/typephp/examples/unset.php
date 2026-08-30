<?php
function main()
{
    $array['hello'] = 'world';
    $name = 'world';
    $object = new stdClass();
    $object->hello = 'world';
    unset($array['hello'], $name, $object->hello);

    var_dump($array, $object);
}
