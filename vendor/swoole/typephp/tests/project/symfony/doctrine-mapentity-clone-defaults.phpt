--TEST--
Symfony Doctrine pattern: clone object and fill defaults with coalesce assign
--FILE--
<?php

class EntityValueResolver
{
}

class DefaultOptions
{
    public function __construct(
        public ?string $class = null,
        public ?string $objectManager = null,
        public ?array $mapping = null,
        public array|string|null $id = null,
        public ?bool $stripNull = null,
    ) {
    }

    public function withDefaults(self $defaults, ?string $class): static
    {
        $clone = clone $this;
        $clone->class ??= class_exists($class ?? '') || interface_exists($class ?? '', false) ? $class : null;
        $clone->objectManager ??= $defaults->objectManager;
        $clone->mapping ??= $defaults->mapping;
        $clone->id ??= $defaults->id;
        $clone->stripNull ??= $defaults->stripNull ?? false;

        return $clone;
    }
}

function main(): void
{
    $defaults = new DefaultOptions(EntityValueResolver::class, 'default', ['id' => 'uuid'], ['uuid'], true);
    $options = new DefaultOptions(null, null, null, 'slug', null);
    $merged = $options->withDefaults($defaults, EntityValueResolver::class);

    var_dump($merged === $options);
    var_dump($merged->class);
    var_dump($merged->objectManager);
    var_dump($merged->mapping);
    var_dump($merged->id);
    var_dump($merged->stripNull);
}
?>
--EXPECT--
bool(false)
string(19) "EntityValueResolver"
string(7) "default"
array(1) {
  ["id"]=>
  string(4) "uuid"
}
string(4) "slug"
bool(true)
