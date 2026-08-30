--TEST--
Symfony Cache style negated static coalesce assignment condition
--FILE--
<?php
class SymfonyApcuSupportedCase
{
    private static ?bool $supported = null;
    public static int $checks = 0;

    public static function probe(): bool
    {
        self::$checks++;

        return false;
    }

    public static function createSystemCache(): string
    {
        if (!self::$supported ??= self::probe()) {
            return 'fallback';
        }

        return 'apcu';
    }
}

function main(): void
{
    var_dump(SymfonyApcuSupportedCase::createSystemCache());
    var_dump(SymfonyApcuSupportedCase::createSystemCache());
    var_dump(SymfonyApcuSupportedCase::$checks);
}
?>
--EXPECT--
string(8) "fallback"
string(8) "fallback"
int(1)
