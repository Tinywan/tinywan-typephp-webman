--TEST--
Class constant as default parameter value (self / class name / FQCN, including constant inherited from an internal parent class)
--FILE--
<?php

class LazyArrayObject extends \ArrayObject
{
    public const NULL_VALUE = null;

    public function test($value1 = self::ARRAY_AS_PROPS, $value2 = LazyArrayObject::ARRAY_AS_PROPS, $value3 = \ArrayObject::ARRAY_AS_PROPS)
    {
        var_dump($value1, $value2, $value3);
    }

    public function nullDefault($value = self::NULL_VALUE)
    {
        var_dump($value);
    }
}

class ParentDefaultValue
{
    public const VALUE = 42;
}

class ChildDefaultValue extends ParentDefaultValue
{
    public function inheritedDefault($value = self::VALUE)
    {
        var_dump($value);
    }
}

function main()
{
    $test = new LazyArrayObject;
    $test->test();
    $test->nullDefault();
    (new ChildDefaultValue)->inheritedDefault();
}
?>
--EXPECT--
int(2)
int(2)
int(2)
NULL
int(42)
