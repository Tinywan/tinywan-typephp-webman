--TEST--
Symfony Serializer pattern: ReflectionProperty checks typed property initialization state
--FILE--
<?php

final class PartialPayload
{
    public ?string $foo;
    public ?string $bar;
    public ?string $nothing = null;
}

function initialized_properties(object $object, array $names): array
{
    $initialized = [];
    foreach ($names as $name) {
        $initialized[$name] = (new ReflectionProperty($object, $name))->isInitialized($object);
    }

    return $initialized;
}

function main(): void
{
    $payload = new PartialPayload();
    $payload->foo = null;

    var_dump(initialized_properties($payload, ['foo', 'bar', 'nothing']));
}
?>
--EXPECT--
array(3) {
  ["foo"]=>
  bool(true)
  ["bar"]=>
  bool(true)
  ["nothing"]=>
  bool(true)
}
