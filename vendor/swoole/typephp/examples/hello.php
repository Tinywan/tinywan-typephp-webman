<?php

function main(): void
{
    echo "Hello World!";
    var_dump(PHP_VERSION);
    global $argv;
    var_dump($argv);

    $date = date('Y-m-d H:i:s', time());
    var_dump($date);
}
