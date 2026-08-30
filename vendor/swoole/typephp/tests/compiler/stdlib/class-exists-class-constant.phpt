--TEST--
class/interface/trait/enum exists with ::class names
--FILE--
<?php
namespace ExistsNames {

    class ExistingClass {}
    interface ExistingInterface {}
    trait ExistingTrait {}
    enum ExistingEnum { case One; }

    function pick_name(array $names, string $key): string
    {
        echo "pick:$key\n";
        return $names[$key];
    }
}

namespace {
    function main(): void
    {
        $names = [
            'class' => ExistsNames\ExistingClass::class,
            'interface' => ExistsNames\ExistingInterface::class,
            'trait' => ExistsNames\ExistingTrait::class,
            'enum' => ExistsNames\ExistingEnum::class,
        ];

        var_dump(class_exists(ExistsNames\pick_name($names, 'class')));
        var_dump(interface_exists(ExistsNames\pick_name($names, 'interface')));
        var_dump(trait_exists(ExistsNames\pick_name($names, 'trait')));
        var_dump(enum_exists(ExistsNames\pick_name($names, 'enum')));
        var_dump(class_exists('ExistsNames\\MissingClass', false));
    }
}
?>
--EXPECT--
pick:class
bool(true)
pick:interface
bool(true)
pick:trait
bool(false)
pick:enum
bool(true)
bool(false)
