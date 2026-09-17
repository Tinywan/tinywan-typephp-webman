<?php

function main(int $argc, array $argv): void
{
    echo "Hello World!";
    var_dump(PHP_VERSION);
    var_dump(php_sapi_name());
    var_dump(php_ini_loaded_file());
    var_dump($argc);
    var_dump($argv);
    var_dump(date('Y-m-d H:i:s', time()));
    var_dump(php_uname());
    var_dump(file_exists('/HELLO.TXT'));
    var_dump(filesize('/HELLO.TXT') > 0);
    var_dump(scandir('/') !== false);
}
