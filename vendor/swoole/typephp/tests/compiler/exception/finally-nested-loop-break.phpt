--TEST--
finally is not injected before break that only leaves nested loop
--FILE--
<?php

function main(): void
{
    try {
        echo "try\n";
        for ($i = 0; $i < 3; $i++) {
            echo "loop:$i\n";
            break;
        }
        echo "after-loop\n";
    } finally {
        echo "finally\n";
    }
}
?>
--EXPECT--
try
loop:0
after-loop
finally
