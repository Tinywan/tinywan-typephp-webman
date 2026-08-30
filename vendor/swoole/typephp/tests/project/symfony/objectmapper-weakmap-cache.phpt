--TEST--
Symfony ObjectMapper pattern: WeakMap object cache with array syntax
--FILE--
<?php

final class Source
{
    public function __construct(public string $name)
    {
    }
}

final class Target
{
    public function __construct(public string $name)
    {
    }
}

function map_source(Source $source, WeakMap $objectMap): Target
{
    if (isset($objectMap[$source])) {
        return $objectMap[$source];
    }

    return $objectMap[$source] = new Target(strtoupper($source->name));
}

function main(): void
{
    $source = new Source('symfony');
    $objectMap = new WeakMap();
    $first = map_source($source, $objectMap);
    $second = map_source($source, $objectMap);

    var_dump($first === $second);
    var_dump($first->name);
    var_dump(count($objectMap));
}
?>
--EXPECT--
bool(true)
string(7) "SYMFONY"
int(1)
