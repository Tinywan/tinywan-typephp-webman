--TEST--
property default value is array with mixed declared type
--FILE--
<?php

declare(strict_types=1);

class Test
{
    private mixed $value = [];

    public function __construct(mixed $value)
    {
        $this->value = $value;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}

function main()
{
    $test = new Test(123);
    var_dump($test->getValue());

    $test = new Test('test');
    var_dump($test->getValue());

    $test = new Test([1, 2, 3]);
    var_dump($test->getValue());

    $test = new Test(new stdClass);
    $v = $test->getValue();
    var_dump($v instanceof stdClass);
    var_dump(get_class($v));
}
?>
--EXPECT--
int(123)
string(4) "test"
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
}
bool(true)
string(8) "stdClass"
