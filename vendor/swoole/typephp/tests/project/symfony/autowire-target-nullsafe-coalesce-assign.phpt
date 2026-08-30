--TEST--
Symfony DI pattern: filtered attribute nullsafe property with coalesce assignment
--FILE--
<?php

class TargetAttribute
{
    public function __construct(public ?string $name)
    {
    }
}

class ServiceReference
{
    public function __construct(
        private string $name,
        private array $attributes,
    ) {
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getName(): ?string
    {
        return $this->name;
    }
}

function resolveTarget(ServiceReference $reference): array
{
    $name = $target = (array_filter($reference->getAttributes(), static fn ($a) => $a instanceof TargetAttribute)[0] ?? null)?->name;

    if (null !== $name ??= $reference->getName()) {
        return [$name, $target];
    }

    return ['missing', $target];
}

function main(): void
{
    var_dump(resolveTarget(new ServiceReference('fallback', [new TargetAttribute('explicit')])));
    var_dump(resolveTarget(new ServiceReference('fallback', [])));
    var_dump(resolveTarget(new ServiceReference('fallback', [new stdClass(), new TargetAttribute(null)])));
}
?>
--EXPECT--
array(2) {
  [0]=>
  string(8) "explicit"
  [1]=>
  string(8) "explicit"
}
array(2) {
  [0]=>
  string(8) "fallback"
  [1]=>
  NULL
}
array(2) {
  [0]=>
  string(8) "fallback"
  [1]=>
  NULL
}
