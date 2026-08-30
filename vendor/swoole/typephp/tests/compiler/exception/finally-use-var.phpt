--TEST--
finally can use caught exception variable when catch rethrows
--FILE--
<?php

function main(): void
{
    try {
        $a = 1;
        throw new RuntimeException('test');
        return;
    } catch (Throwable $e) {
        echo 'Caught exception: ', $e->getMessage(), "\n";
        throw $e;
    } finally {
        var_dump($a);
        echo 'Finally exception: ', $e->getMessage(), "\n";
    }
}
?>
--EXPECTF--
Caught exception: test
int(1)
Finally exception: test

Fatal error: Uncaught RuntimeException: test in %A
