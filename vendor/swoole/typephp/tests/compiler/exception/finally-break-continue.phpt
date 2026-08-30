--TEST--
finally runs before break and continue leave try block
--FILE--
<?php

function main(): void
{
    for ($i = 0; $i < 3; $i++) {
        try {
            echo "try:$i\n";
            if ($i === 0) {
                continue;
            }
            if ($i === 1) {
                break;
            }
        } finally {
            echo "finally:$i\n";
        }
        echo "after:$i\n";
    }

    echo "done\n";
}
?>
--EXPECT--
try:0
finally:0
try:1
finally:1
done
