--TEST--
Chained assignment with array append ([]), property write, and variable write as rvalue inside constructor argument
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
    public $arr = [];

    public $value;

    public function __construct()
    {
        $arr = [0];
        new Test2($this->arr[] = $arr[] = $this->value = $value = 1);
        var_dump($this->value, $value, $this->arr, $arr);
    }
}

function main()
{
    new Test;
}
?>
--EXPECT--
int(1)
int(1)
int(1)
array(1) {
  [0]=>
  int(1)
}
array(2) {
  [0]=>
  int(0)
  [1]=>
  int(1)
}
