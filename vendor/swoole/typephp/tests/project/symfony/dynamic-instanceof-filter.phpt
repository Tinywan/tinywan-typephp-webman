--TEST--
Symfony pattern: array_filter with dynamic instanceof class name
--XFAIL--
Known AOT bug: objects filtered by dynamic instanceof can lose their precise class type before closure property reads.
--FILE--
<?php

class SymfonyLikeControllerAttribute
{
    public function __construct(public string $name)
    {
    }
}

class SymfonyLikeOtherAttribute
{
}

class SymfonyLikeControllerEvent
{
    public function __construct(private array $attributes)
    {
    }

    public function getAttributes(string $className): array
    {
        return array_values(array_filter($this->attributes, static fn ($attr) => $attr instanceof $className));
    }
}

function main(): void
{
    $event = new SymfonyLikeControllerEvent([
        new SymfonyLikeOtherAttribute(),
        new SymfonyLikeControllerAttribute('template'),
        new SymfonyLikeControllerAttribute('cache'),
    ]);

    var_dump(array_map(static fn ($attribute) => $attribute->name, $event->getAttributes(SymfonyLikeControllerAttribute::class)));
}
?>
--EXPECT--
array(2) {
  [0]=>
  string(8) "template"
  [1]=>
  string(5) "cache"
}
