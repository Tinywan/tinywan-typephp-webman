--TEST--
BigInt literal parsing and output
--FILE--
<?php
function main(): void {
    $a = 12345678901234567890;
    echo $a->toString();
    echo "\n";
    $b = 99999999999999999999;
    echo $b->toString();
}
?>
--EXPECT--
12345678901234567890
99999999999999999999
