--TEST--
??= evaluates a side-effecting property receiver exactly once, on both branches
--FILE--
<?php
class Box { public mixed $value = null; }

function receiver(object $b): object { echo "RECV\n"; return $b; }
function sideEffect(): int { echo "SIDE\n"; return 41; }

function main(): void
{
    $box = new Box();
    receiver($box)->value ??= sideEffect() + 1;
    var_dump($box->value);
    receiver($box)->value ??= sideEffect() + 1;
    var_dump($box->value);
}
?>
--EXPECT--
RECV
SIDE
int(42)
RECV
int(42)
