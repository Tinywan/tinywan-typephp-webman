--TEST--
An instance property named static is not treated as late static binding
--FILE--
<?php

class StaticPropertyValue
{
    public string $static = 'value';
}

class StaticPropertyReader
{
    public function read(?StaticPropertyValue $value): void
    {
        var_dump($value->static);
        var_dump($value?->static);
    }
}

function main(): void
{
    (new StaticPropertyReader())->read(new StaticPropertyValue());
}

?>
--EXPECT--
string(5) "value"
string(5) "value"
