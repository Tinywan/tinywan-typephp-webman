--TEST--
Return value by trait (method)
--FILE--
<?php
Trait T1
{
    public function &getRefValue()
    {
        return $this->value;
    }
}

class Test
{
    use T1;

    private $value = 1;

    public function getValue()
    {
        return $this->value;
    }
}

function main()
{
    $test = new Test;
    var_dump($test->getValue());
    var_dump($refValue = &$test->getRefValue());
    $refValue = 2;
    var_dump($test->getValue());
    var_dump($test->getRefValue());
}
?>
--EXPECT--
int(1)
int(1)
int(2)
int(2)
