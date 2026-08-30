--TEST--
Symfony pattern: event name defaults to runtime object class with ??=
--FILE--
<?php

class SymfonyLikeEvent
{
}

class SymfonyLikeChildEvent extends SymfonyLikeEvent
{
}

function dispatch(object $event, ?string $eventName = null): string
{
    $eventName ??= $event::class;

    return $eventName;
}

function main(): void
{
    var_dump(dispatch(new SymfonyLikeEvent()));
    var_dump(dispatch(new SymfonyLikeChildEvent()));
    var_dump(dispatch(new SymfonyLikeChildEvent(), 'custom.event'));
}
?>
--EXPECT--
string(16) "SymfonyLikeEvent"
string(21) "SymfonyLikeChildEvent"
string(12) "custom.event"
