<?php
use native_types;

function main()
{
    $x = 100;
    unset($x);
    var_dump($x);
}
