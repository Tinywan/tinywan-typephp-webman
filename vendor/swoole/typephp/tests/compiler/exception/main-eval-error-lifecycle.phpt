--TEST--
Uncaught main error does not access request memory after php::eval
--AOT_ARGS--
-O2
--FILE--
<?php

function main(): void
{
    throw new RuntimeException('main lifecycle error');
}
?>
--EXPECTF--
Fatal error: Uncaught RuntimeException: main lifecycle error in %smain-eval-error-lifecycle.php:%d
Stack trace:
%A
