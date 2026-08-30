--TEST--
Symfony pattern: arrow function throw expression
--ENV--
USE_ZEND_ALLOC=0
--FILE--
<?php

function main(): void
{
    $handler = static fn () => throw new RuntimeException('failed');

    try {
        $handler();
    } catch (Throwable $e) {
        var_dump($e::class);
        var_dump($e->getMessage());
    }
}
?>
--EXPECT--
string(16) "RuntimeException"
string(6) "failed"
