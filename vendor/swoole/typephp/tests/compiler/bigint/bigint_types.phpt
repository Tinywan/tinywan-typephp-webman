--TEST--
use bigint_types — integer literals auto-converted to BigInt
--FILE--
<?php
declare(strict_types=1);
use bigint_types;

function main(): void {
    // Simple int literal → BigInt
    $a = 42;
    echo $a->toString(); echo "\n";

    // BigInt + Int literal (auto BigInt)
    $b = $a + 10;
    echo $b->toString(); echo "\n";

    // BigInt * BigInt
    $c = $a * 3;
    echo $c->toString(); echo "\n";

    // Int literal in arithmetic
    $d = 100 + 200;
    echo $d->toString(); echo "\n";
}
?>
--EXPECT--
42
52
126
300
