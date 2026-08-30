--TEST--
PHP 8.5 warns when NAN is coerced to string
--SKIPIF--
<?php
if (PHP_VERSION_ID < 80500) {
    die('skip requires PHP 8.5 or newer');
}
?>
--FILE--
<?php
function main(): void
{
    echo NAN . "\n";
}
?>
--EXPECTF--
Warning: unexpected NAN value was coerced to string in %s on line %d
NAN
