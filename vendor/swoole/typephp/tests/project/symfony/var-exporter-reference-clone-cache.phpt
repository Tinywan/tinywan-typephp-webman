--TEST--
Symfony pattern: reference cache with clone and null coalescing
--SKIPIF--
<?php
exit("skip: assigning a reference from a complex static property expression is not supported in AOT");
?>
--FILE--
<?php

final class PrototypeRegistry
{
    public static array $prototypes = [];

    public static function create(string $class): object
    {
        echo "create:$class\n";
        return new $class('created');
    }
}

final class ExportedService
{
    public function __construct(public string $name)
    {
    }
}

function getPrototype(string $class): object
{
    return clone (($p = &PrototypeRegistry::$prototypes)[$class] ?? PrototypeRegistry::create($class));
}

function main(): void
{
    $class = ExportedService::class;

    $first = getPrototype($class);
    PrototypeRegistry::$prototypes[$class] = new ExportedService('cached');
    $second = getPrototype($class);

    $first->name = 'mutated';
    $second->name = 'changed';

    var_dump($first->name);
    var_dump($second->name);
    var_dump(PrototypeRegistry::$prototypes[$class]->name);
}
?>
--EXPECT--
create:ExportedService
string(7) "mutated"
string(7) "changed"
string(6) "cached"
