<?php
//$array = [1, 3, 4, 5, 6];
//
//var_dump($array[2.3]);
//
//var_dump($array['3.2']);
//
//$str = "hello world\n";
//var_dump($str[99]);
//var_dump($str[-3]);

function foo()
{
    global $gv;
    $gv = 100;
}

function main()
{
    var_dump($_SERVER);
    foo();

    global $gv;
    var_dump($gv);

    global $gv;
    var_dump($gv);
}
