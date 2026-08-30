--TEST--
Symfony Serializer pattern: supported type matching for ClassName array collections
--FILE--
<?php

class Animal
{
}

class Dog extends Animal
{
}

class Cat extends Animal
{
}

function supports_type(string $class, array $supportedTypes): array
{
    $genericType = class_exists($class) || interface_exists($class, false) ? 'object' : '*';
    $doesClassRepresentCollection = str_ends_with($class, '[]');
    $matches = [];

    foreach ($supportedTypes as $supportedType => $isCacheable) {
        if (in_array($supportedType, ['*', 'object'], true)
            || $class !== $supportedType && ('object' !== $genericType || !is_subclass_of($class, $supportedType))
            && !($doesClassRepresentCollection && str_ends_with($supportedType, '[]') && is_subclass_of(strstr($class, '[]', true), strstr($supportedType, '[]', true)))
        ) {
            continue;
        }

        $matches[$supportedType] = $isCacheable;
    }

    return $matches;
}

function main(): void
{
    var_dump(supports_type(Dog::class, [Animal::class => true, Cat::class => false, '*' => null]));
    var_dump(supports_type(Dog::class.'[]', [Animal::class.'[]' => true, Cat::class.'[]' => false, 'object' => null]));
}
?>
--EXPECT--
array(1) {
  ["Animal"]=>
  bool(true)
}
array(1) {
  ["Animal[]"]=>
  bool(true)
}
