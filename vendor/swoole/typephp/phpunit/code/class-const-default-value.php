<?php

class LazyArrayObject extends \ArrayObject
{
    public function test($value1 = self::ARRAY_AS_PROPS, $value2 = LazyArrayObject::ARRAY_AS_PROPS, $value3 = \ArrayObject::ARRAY_AS_PROPS)
    {
        var_dump($value1, $value2, $value3);
    }
}

function main()
{
    $test = new LazyArrayObject;
    $test->test();
}
