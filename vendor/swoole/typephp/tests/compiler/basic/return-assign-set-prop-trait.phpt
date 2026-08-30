--TEST--
return assign array dim
--SKIPIF--
--FILE--
<?php

Trait TestReturnTrait {
    protected ?array $array = null;

    protected function makeArray(): array {
        return [1, 3, 4];
    }
    public function test()
    {
        if ($this->array !== null) {
            return $this->array;
        }
        return $this->array = $this->makeArray();
    }
}

class TestReturnAssignSetProp
{
    use TestReturnTrait;
}

function main()
{
    $obj = new TestReturnAssignSetProp;
    var_dump($obj->test());
}
?>
--EXPECT--
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(3)
  [2]=>
  int(4)
}