--TEST--
Symfony ErrorHandler pattern: dynamic static property name with multidimensional isset
--FILE--
<?php

class FinalMemberRegistry
{
    private static array $finalConstants = [
        'ParentClass' => [
            'STATUS' => 'ParentClass::STATUS',
        ],
    ];

    private static array $finalProperties = [
        'ParentClass' => [
            'name' => 'ParentClass::$name',
        ],
    ];

    public static function lookup(string $type, string $class, string $name): ?string
    {
        if (isset(self::${$type}[$class][$name])) {
            return self::${$type}[$class][$name];
        }

        return null;
    }
}

function main(): void
{
    var_dump(FinalMemberRegistry::lookup('finalConstants', 'ParentClass', 'STATUS'));
    var_dump(FinalMemberRegistry::lookup('finalProperties', 'ParentClass', 'name'));
    var_dump(FinalMemberRegistry::lookup('finalProperties', 'ParentClass', 'missing'));
}
?>
--EXPECT--
string(19) "ParentClass::STATUS"
string(18) "ParentClass::$name"
NULL
