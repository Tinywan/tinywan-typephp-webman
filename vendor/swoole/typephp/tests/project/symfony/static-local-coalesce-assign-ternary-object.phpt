--TEST--
Symfony Serializer pattern: static local coalesce assignment with ternary object cache
--FILE--
<?php

class LocalResolver
{
    public function resolve(string $value): string
    {
        return 'resolved:'.$value;
    }
}

function resolveWhenAvailable(?string $value, bool $enabled): ?string
{
    static $resolver;

    if (null !== $value && $resolver ??= $enabled && class_exists(LocalResolver::class) ? new LocalResolver() : false) {
        return $resolver->resolve($value);
    }

    return null;
}

function main(): void
{
    var_dump(resolveWhenAvailable(null, true));
    var_dump(resolveWhenAvailable('first', true));
    var_dump(resolveWhenAvailable('second', false));
}
?>
--EXPECT--
NULL
string(14) "resolved:first"
string(15) "resolved:second"
