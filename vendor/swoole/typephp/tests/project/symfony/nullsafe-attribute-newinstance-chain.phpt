--TEST--
Symfony pattern: nullsafe attribute newInstance chain
--FILE--
<?php

#[Attribute(Attribute::TARGET_CLASS)]
class TaggedItem
{
    public function __construct(public int $priority = 0)
    {
    }
}

#[TaggedItem(priority: 20)]
class TaggedService
{
}

class UntaggedService
{
}

function priorityOf(string $class): ?int
{
    return ((new ReflectionClass($class))->getAttributes(TaggedItem::class)[0] ?? null)?->newInstance()->priority;
}

function main(): void
{
    var_dump(priorityOf(TaggedService::class));
    var_dump(priorityOf(UntaggedService::class));
}
?>
--EXPECT--
int(20)
NULL
