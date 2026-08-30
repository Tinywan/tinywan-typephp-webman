--TEST--
Symfony pattern: set_error_handler with static arrow function throwing
--ENV--
USE_ZEND_ALLOC=0
--FILE--
<?php

function main(): void
{
    set_error_handler(static fn ($type, $message, $file, $line) => throw new ErrorException($message, 0, $type, $file, $line));

    try {
        trigger_error('symfony warning', E_USER_WARNING);
    } catch (Throwable $e) {
        var_dump($e::class);
        var_dump($e->getMessage());
    } finally {
        restore_error_handler();
    }
}
?>
--EXPECT--
string(14) "ErrorException"
string(15) "symfony warning"
