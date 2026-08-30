--TEST--
Symfony pattern: static property array spread merge assignment
--FILE--
<?php

class CasterRegistry
{
    private static array $casters = [
        'default' => 'base',
    ];

    public static function add(array $casters): void
    {
        self::$casters = [...self::$casters, ...$casters];
    }

    public static function all(): array
    {
        return self::$casters;
    }
}

function main(): void
{
    CasterRegistry::add(['a' => 'A']);
    CasterRegistry::add(['default' => 'override', 'b' => 'B']);

    var_dump(CasterRegistry::all());
}
?>
--EXPECT--
array(3) {
  ["default"]=>
  string(8) "override"
  ["a"]=>
  string(1) "A"
  ["b"]=>
  string(1) "B"
}
