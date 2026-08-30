--TEST--
PHP 8.5 warns when pow overflow is narrowed to a native int
--SKIPIF--
<?php
if (PHP_VERSION_ID < 80500) {
    die('skip requires PHP 8.5 or newer');
}
?>
--FILE--
<?php
use native_types;
function main(): void
{
    $value = 2 ** 80;
    echo $value, PHP_EOL;
}
?>
--EXPECTF--
Warning: The float %s is not representable as an int, cast occurred in %s on line %d
0
