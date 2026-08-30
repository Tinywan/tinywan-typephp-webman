--TEST--
Symfony pattern: normalize array listener method with ??=
--FILE--
<?php

class SymfonyLikeListener
{
    public function __invoke(): string
    {
        return 'invoked';
    }

    public function onEvent(): string
    {
        return 'event';
    }
}

function normalizeListener(array $listener): string
{
    $listener[1] ??= '__invoke';

    return $listener[0]::class.'::'.$listener[1];
}

function main(): void
{
    var_dump(normalizeListener([new SymfonyLikeListener()]));
    var_dump(normalizeListener([new SymfonyLikeListener(), 'onEvent']));
}
?>
--EXPECT--
string(29) "SymfonyLikeListener::__invoke"
string(28) "SymfonyLikeListener::onEvent"
