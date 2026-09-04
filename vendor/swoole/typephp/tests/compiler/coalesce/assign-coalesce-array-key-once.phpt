--TEST--
??= evaluates a side-effecting array key exactly once, on both branches
--FILE--
<?php
function arrayKey(): string { echo "KEY\n"; return "k"; }
function sideEffect(): int { echo "SIDE\n"; return 41; }

function main(): void
{
    $arr = [];
    $arr[arrayKey()] ??= sideEffect() + 1;
    var_dump($arr["k"]);
    $arr[arrayKey()] ??= sideEffect() + 1;
    var_dump($arr["k"]);
}
?>
--EXPECT--
KEY
SIDE
int(42)
KEY
int(42)
