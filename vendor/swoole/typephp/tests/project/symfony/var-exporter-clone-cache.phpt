--TEST--
Symfony pattern: clone object from static cache with null coalescing
--FILE--
<?php

final class CloneRegistry
{
    public static array $prototypes = [];

    public static function create(string $class): object
    {
        echo "create:$class\n";
        return new $class('created');
    }
}

final class CloneableService
{
    public function __construct(public string $name)
    {
    }
}

function getCachedPrototype(string $class): object
{
    return clone (CloneRegistry::$prototypes[$class] ?? CloneRegistry::create($class));
}

function main(): void
{
    $class = CloneableService::class;

    $first = getCachedPrototype($class);
    CloneRegistry::$prototypes[$class] = new CloneableService('cached');
    $second = getCachedPrototype($class);

    $first->name = 'mutated';
    $second->name = 'changed';

    var_dump($first->name);
    var_dump($second->name);
    var_dump(CloneRegistry::$prototypes[$class]->name);
}
?>
--EXPECT--
create:CloneableService
string(7) "mutated"
string(7) "changed"
string(6) "cached"
