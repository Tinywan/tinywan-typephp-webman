--TEST--
throw expression in coalesce and ternary
--FILE--
<?php

function require_value(?string $value): string
{
    return $value ?? throw new InvalidArgumentException('missing');
}

function pick_value(bool $ok): string
{
    return $ok ? 'ok' : throw new RuntimeException('bad');
}

function main(): void
{
    var_dump(require_value('present'));
    var_dump(pick_value(true));

    try {
        require_value(null);
    } catch (Throwable $e) {
        echo get_class($e) . ':' . $e->getMessage() . "\n";
    }

    try {
        pick_value(false);
    } catch (Throwable $e) {
        echo get_class($e) . ':' . $e->getMessage() . "\n";
    }
}
?>
--EXPECT--
string(7) "present"
string(2) "ok"
InvalidArgumentException:missing
RuntimeException:bad
