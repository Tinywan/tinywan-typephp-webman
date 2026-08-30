--TEST--
declare encoding UTF-8 is accepted
--FILE--
<?php
declare(encoding="UTF-8");

function main(): void
{
    echo "encoding-ok\n";
}
?>
--EXPECT--
encoding-ok
