<?php
function main(int $argc, array $argv)
{
    var_dump($argc, $argv);
    fn1();
    $rs = fn_test(199, 189);
    $array = [1, 3, 5];
    foreach ($array as $r) {
        echo $r;
    }
    var_dump(gettype($rs));

    var_dump("c++ class:");
    $o = new Foo(1000);
    var_dump($o->bar(123, 342));
}