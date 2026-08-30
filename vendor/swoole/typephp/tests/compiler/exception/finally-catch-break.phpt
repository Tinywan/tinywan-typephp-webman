--TEST--
finally runs before break leaves catch block
--FILE--
<?php

function main(): void
{
    for ($i = 0; $i < 2; $i++) {
        try {
            echo "try:$i\n";
            throw new RuntimeException('stop');
        } catch (RuntimeException $e) {
            echo "catch:$i\n";
            break;
        } finally {
            echo "finally:$i\n";
        }
    }

    echo "done\n";
}
?>
--EXPECT--
try:0
catch:0
finally:0
done
