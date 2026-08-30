--TEST--
Symfony Filesystem style dynamic function call boxed by first-class error handler
--FILE--
<?php
class SymfonyFilesystemBoxCase
{
    private static ?array $lastError = null;

    private static function assertFunctionExists(string $func): void
    {
        if (!function_exists($func)) {
            throw new RuntimeException(sprintf('Unable to perform filesystem operation because the "%s()" function has been disabled.', $func));
        }
    }

    private static function handleError(int $type, string $message): bool
    {
        self::$lastError = [$type, $message];

        return true;
    }

    public static function box(string $func, mixed ...$args): mixed
    {
        self::assertFunctionExists($func);

        self::$lastError = null;
        set_error_handler(self::handleError(...));
        try {
            return $func(...$args);
        } finally {
            restore_error_handler();
        }
    }

    public static function getLastError(): ?array
    {
        return self::$lastError;
    }
}

function main(): void
{
    var_dump(SymfonyFilesystemBoxCase::box('strtoupper', 'abc'));
    var_dump(SymfonyFilesystemBoxCase::getLastError());

    try {
        SymfonyFilesystemBoxCase::box('definitely_missing_function');
    } catch (RuntimeException $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
string(3) "ABC"
NULL
Unable to perform filesystem operation because the "definitely_missing_function()" function has been disabled.
