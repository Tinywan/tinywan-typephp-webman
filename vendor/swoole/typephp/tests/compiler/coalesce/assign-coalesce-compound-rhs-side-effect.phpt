--TEST--
??= does not evaluate a compound side-effecting RHS when the target is set
--FILE--
<?php
declare(strict_types=1);

function sideEffect(): int
{
    echo "side effect!\n";
    return 41;
}

function main(): void
{
    $a = 1;
    $a ??= sideEffect() + 1;
    var_dump($a);

    $b = null;
    $b ??= sideEffect() + 1;
    var_dump($b);

    $c = 'set';
    $c ??= sideEffect() . '-suffix';
    var_dump($c);
}
?>
--EXPECT--
int(1)
side effect!
int(42)
string(3) "set"
