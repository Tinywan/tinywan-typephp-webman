--TEST--
pow int overflow
--FILE--
<?php
use native_types;
function main()
{
    // PHP 8.5 warns when the overflowing float is narrowed to a native int.
    // This test covers the resulting value; a version-specific companion test
    // covers the warning so this expectation remains stable on PHP 8.4/8.5.
    error_reporting(E_ERROR);
    $a = 2 ** 80;
    echo $a, PHP_EOL;
}
?>
--EXPECT--
0
