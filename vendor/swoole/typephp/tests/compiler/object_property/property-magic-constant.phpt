--TEST--
PHP 8.4 __PROPERTY__ resolves in property contexts only
--FILE--
<?php

#[Attribute(Attribute::TARGET_PROPERTY)]
class PropertyName
{
    public function __construct(public string $name)
    {
    }
}

class PropertyMagicConstants
{
    public string $first = __PROPERTY__, $second = __PROPERTY__;

    #[PropertyName(__PROPERTY__)]
    public string $annotated = __PROPERTY__;

    public string $hooked {
        get => __PROPERTY__;
        set {
            var_dump(__PROPERTY__);
        }
    }

    private string $nested {
        get => (function (): string {
            return __PROPERTY__;
        })();
    }

    public function outsideProperty(): string
    {
        return __PROPERTY__;
    }

    public function nestedProperty(): string
    {
        return $this->nested;
    }
}

function main(): void
{
    $object = new PropertyMagicConstants();
    var_dump($object->first);
    var_dump($object->second);
    var_dump($object->annotated);
    $property = new ReflectionProperty(PropertyMagicConstants::class, 'annotated');
    var_dump($property->getAttributes(PropertyName::class)[0]->getArguments()[0]);
    var_dump($object->hooked);
    $object->hooked = 'ignored';
    var_dump($object->outsideProperty());
    var_dump($object->nestedProperty());
    var_dump(__PROPERTY__);
}
?>
--EXPECT--
string(5) "first"
string(6) "second"
string(9) "annotated"
string(9) "annotated"
string(6) "hooked"
string(6) "hooked"
string(0) ""
string(0) ""
string(0) ""
