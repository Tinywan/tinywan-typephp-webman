--TEST--
Method returning by reference can forward another by-reference method call
--FILE--
<?php
class Test
{
    private $value = 1;

    public function &getValue()
    {
        return $this->value;
    }

    public function &getRefValue()
    {
        return $this->getValue();
    }
}

function main()
{
    $test = new Test;
    var_dump($test->getRefValue());
    $ref = &$test->getRefValue();
    $ref = 2;
    var_dump($test->getValue());
}

// main();
?>
--EXPECT--
int(1)
int(2)
