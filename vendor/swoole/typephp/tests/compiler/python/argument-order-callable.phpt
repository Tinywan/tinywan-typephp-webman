--TEST--
Python arguments evaluate left-to-right once and accept TypePHP callables
--SKIPIF--
<?php
if (!extension_loaded('phpy')) {
    die('skip phpy extension is not loaded');
}
?>
--FILE--
<?php

use Python\sys;
use Python\typephp_protocol as protocol;

function mark(int $value): int
{
    echo "mark:$value\n";
    return $value;
}

function main(): void
{
    $power = python\pow(mark(2), mark(3));
    var_dump(python\scalar($power)->toInt());

    $mapped = python\map(fn (int $value): int => $value * 2, [1, 2, 3]);
    var_dump(python\scalar(python\sum($mapped))->toInt());

    sys\path->append(__DIR__ . '/lib');
    $callbackResult = protocol\callback_with_kwargs(
        fn (string $left, int $right): string => "$left:$right"
    );
    var_dump(python\scalar($callbackResult)->toString());
}
?>
--EXPECT--
mark:2
mark:3
int(8)
int(12)
string(6) "left:7"
