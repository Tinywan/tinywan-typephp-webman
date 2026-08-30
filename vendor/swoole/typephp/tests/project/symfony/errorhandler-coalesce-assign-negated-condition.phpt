--TEST--
Symfony ErrorHandler pattern: coalesce assign inside negated condition
--FILE--
<?php

function resolve_handler(?Closure $handler, ?Closure $defaultHandler): string
{
    if (!$handler ??= $defaultHandler) {
        return 'none';
    }

    return $handler();
}

function main(): void
{
    var_dump(resolve_handler(null, static fn () => 'default'));
    var_dump(resolve_handler(static fn () => 'custom', static fn () => 'default'));
    var_dump(resolve_handler(null, null));
}
?>
--EXPECT--
string(7) "default"
string(6) "custom"
string(4) "none"
