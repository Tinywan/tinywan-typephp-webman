--TEST--
Do-while condition evaluates side effects after continue
--FILE--
<?php
function keepGoing(int $i): bool
{
    echo "cond:$i\n";
    return $i < 3;
}

function main(): void
{
    $i = 0;
    do {
        echo "body:$i\n";
        $i++;
        if ($i < 2) {
            continue;
        }
    } while (keepGoing($i));
}
?>
--EXPECT--
body:0
cond:1
body:1
cond:2
body:2
cond:3
