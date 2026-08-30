--TEST--
finally runs before goto leaves try block
--FILE--
<?php

function main(): void
{
    try {
        echo "try\n";
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
finally
done
