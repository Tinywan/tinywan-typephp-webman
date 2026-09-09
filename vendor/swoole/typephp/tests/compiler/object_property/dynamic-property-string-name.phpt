--TEST--
dynamic property access preserves string, referenced-string, and converted names
--FILE--
<?php

final class DynamicNameBag
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

function readName(DynamicNameBag $bag, mixed $name): mixed
{
    return $bag->{$name};
}

function writeName(DynamicNameBag $bag, mixed $name, mixed $value): void
{
    $bag->{$name} = $value;
}

function main(): void
{
    $bag = new DynamicNameBag();
    $name = std::any('answer');
    writeName($bag, $name, 42);
    var_dump(readName($bag, $name));

    $alias = &$name;
    var_dump(readName($bag, $alias));

    writeName($bag, 7, 'seven');
    var_dump(readName($bag, 7));
}
?>
--EXPECT--
int(42)
int(42)
string(5) "seven"
