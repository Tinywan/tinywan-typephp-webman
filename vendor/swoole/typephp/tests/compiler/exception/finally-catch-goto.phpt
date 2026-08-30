--TEST--
finally runs before goto leaves catch block
--FILE--
<?php

function main(): void
{
    try {
        echo "try\n";
        throw new RuntimeException('jump');
    } catch (RuntimeException $e) {
        echo "catch\n";
        goto done;
    } finally {
        echo "finally\n";
    }

    echo "unreachable\n";

    done:
    echo "done\n";
}
?>
--EXPECT--
try
catch
finally
done
