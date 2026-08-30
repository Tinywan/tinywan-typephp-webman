--TEST--
Multi-variable assignment: property assignment as rvalue in constructor argument expression
--FILE--
<?php
class Test2
{
    public function __construct($value2)
    {
        var_dump($value2);
    }
}

class Test
{
    public $value;

    public $value2;

    public $value3;

    public $arr = [1];

    public function __construct()
    {
        $this->value = new Test2($this->arr[0] = $this->value2 = 123);
        var_dump($this->value2, $this->arr);
        $this->value = new Test2($this->arr[0] = $this->value3 = $this->value2 = 456);
        var_dump($this->value2, $this->value3, $this->arr);
    }
}

function main()
{
    new Test;
}
?>
--EXPECT--
int(123)
int(123)
array(1) {
  [0]=>
  int(123)
}
int(456)
int(456)
int(456)
array(1) {
  [0]=>
  int(456)
}
