--TEST--
Symfony pattern: set_error_handler with first-class static method and finally restore
--FILE--
<?php

final class BoxedErrorHandler
{
    private static ?string $lastError = null;

    public static function handleError(int $type, string $message): bool
    {
        self::$lastError = $type.':'.$message;
        return true;
    }

    public static function call(callable $callback): mixed
    {
        set_error_handler(self::handleError(...));

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }

    public static function lastError(): ?string
    {
        return self::$lastError;
    }
}

function main(): void
{
    $result = BoxedErrorHandler::call(static function (): string {
        trigger_error('boxed-warning', E_USER_WARNING);
        return 'done';
    });

    var_dump($result);
    var_dump(BoxedErrorHandler::lastError());
}
?>
--EXPECT--
string(4) "done"
string(17) "512:boxed-warning"
