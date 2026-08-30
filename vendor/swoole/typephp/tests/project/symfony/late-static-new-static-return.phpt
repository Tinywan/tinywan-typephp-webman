--TEST--
Symfony pattern: late static binding with new static and static return type
--FILE--
<?php

class BaseFactory
{
    public string $name = 'base';

    public static function create(string $name): static
    {
        $instance = new static();
        $instance->name = $name;

        return $instance;
    }
}

class ChildFactory extends BaseFactory
{
    public string $extra = 'child';
}

function main(): void
{
    $base = BaseFactory::create('root');
    $child = ChildFactory::create('leaf');

    var_dump($base::class);
    var_dump($base->name);
    var_dump($child::class);
    var_dump($child->name);
    var_dump($child->extra);
}
?>
--EXPECT--
string(11) "BaseFactory"
string(4) "root"
string(12) "ChildFactory"
string(4) "leaf"
string(5) "child"
