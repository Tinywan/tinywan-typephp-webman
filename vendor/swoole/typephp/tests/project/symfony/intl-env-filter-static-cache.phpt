--TEST--
Symfony Intl pattern: env fallback with filter_var and static coalesce cache
--FILE--
<?php
class SymfonyIntlEnvFlag
{
    private static ?bool $withUserAssigned = null;

    public static function reset(): void
    {
        self::$withUserAssigned = null;
    }

    public static function withUserAssigned(): bool
    {
        return self::$withUserAssigned ??= filter_var(
            $_ENV['SYMFONY_INTL_WITH_USER_ASSIGNED'] ?? $_SERVER['SYMFONY_INTL_WITH_USER_ASSIGNED'] ?? getenv('SYMFONY_INTL_WITH_USER_ASSIGNED'),
            FILTER_VALIDATE_BOOLEAN
        );
    }
}

function main(): void
{
    unset($_ENV['SYMFONY_INTL_WITH_USER_ASSIGNED'], $_SERVER['SYMFONY_INTL_WITH_USER_ASSIGNED']);
    putenv('SYMFONY_INTL_WITH_USER_ASSIGNED=1');
    var_dump(SymfonyIntlEnvFlag::withUserAssigned());

    putenv('SYMFONY_INTL_WITH_USER_ASSIGNED=0');
    var_dump(SymfonyIntlEnvFlag::withUserAssigned());

    SymfonyIntlEnvFlag::reset();
    $_SERVER['SYMFONY_INTL_WITH_USER_ASSIGNED'] = 'false';
    var_dump(SymfonyIntlEnvFlag::withUserAssigned());

    SymfonyIntlEnvFlag::reset();
    $_ENV['SYMFONY_INTL_WITH_USER_ASSIGNED'] = 'true';
    var_dump(SymfonyIntlEnvFlag::withUserAssigned());

    unset($_ENV['SYMFONY_INTL_WITH_USER_ASSIGNED'], $_SERVER['SYMFONY_INTL_WITH_USER_ASSIGNED']);
    putenv('SYMFONY_INTL_WITH_USER_ASSIGNED');
}
?>
--EXPECT--
bool(true)
bool(true)
bool(false)
bool(true)
