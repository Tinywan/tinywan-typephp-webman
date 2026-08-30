--TEST--
Typed parameter default value from an unresolvable (external) class constant
--FILE--
<?php

class TypedDefault
{
    public function run(
        int $value = \ArrayObject::ARRAY_AS_PROPS,
        float $floatValue = \ArrayObject::ARRAY_AS_PROPS,
        string $format = \DateTime::ATOM,
        int $composite = 1 | \ArrayObject::ARRAY_AS_PROPS,
        mixed $variant = \ArrayObject::STD_PROP_LIST,
    )
    {
        var_dump($value, $floatValue, $format, $composite, $variant);
    }
}

function main()
{
    (new TypedDefault)->run();
}
?>
--EXPECT--
int(2)
float(2)
string(13) "Y-m-d\TH:i:sP"
int(3)
int(1)
