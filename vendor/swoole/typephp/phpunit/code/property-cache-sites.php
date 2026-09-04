<?php

function propertyCacheReceiver(object $object): object
{
    return $object;
}

function propertyCacheSites(object $object, string $name, mixed $value): mixed
{
    $first = $object->named;
    $object->named = $value;
    propertyCacheReceiver($object)->other = $value;

    $dynamic = $object->{$name};
    $object->{$name} = $value;
    return [$first, $dynamic];
}

final class DirectMagicPropertySites
{
    private array $values = [];

    public function __get(string $name): mixed
    {
        return $this->values[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->values[$name] = $value;
    }
}

function directMagicPropertySites(mixed $value): mixed
{
    $object = new DirectMagicPropertySites();
    $current = $object->missing;
    $object->missing = $value;
    return $current;
}
