--TEST--
Symfony pattern: dynamic class instantiation with null coalesce default
--FILE--
<?php

class SymfonyLikeNamedService
{
    public function __construct(public string $name = 'default')
    {
    }
}

function createService(?string $class, ?string $name = null): object
{
    $class ??= SymfonyLikeNamedService::class;

    return new $class($name ?? 'fallback');
}

function main(): void
{
    var_dump(createService(null)->name);
    var_dump(createService(SymfonyLikeNamedService::class, 'custom')->name);
}
?>
--EXPECT--
string(8) "fallback"
string(6) "custom"
