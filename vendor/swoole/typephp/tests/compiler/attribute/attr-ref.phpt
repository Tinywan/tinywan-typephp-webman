--TEST--
object link operator
--FILE--
<?php

class TestAttrRef
{
    private $k = null;
    public $nums = null;

    /**
     * @param Integer $k
     * @param Integer[] $nums
     */
    function __construct($k, $nums)
    {
        $this->k = $k;
        $this->nums = $nums;
        rsort($this->nums);
        var_dump($this->nums);
        $this->nums = [34, 5, 8, 2];
        var_dump($this->nums);
        $this->nums = array_slice($this->nums, 0, $k);
        var_dump($this->nums);
    }
}

function main()
{
    $k = 3;
    $nums = [4, 5, 8, 2];
    $obj = new TestAttrRef($k, $nums);
}
?>
--EXPECT--
array(4) {
  [0]=>
  int(8)
  [1]=>
  int(5)
  [2]=>
  int(4)
  [3]=>
  int(2)
}
array(4) {
  [0]=>
  int(34)
  [1]=>
  int(5)
  [2]=>
  int(8)
  [3]=>
  int(2)
}
array(3) {
  [0]=>
  int(34)
  [1]=>
  int(5)
  [2]=>
  int(8)
}
