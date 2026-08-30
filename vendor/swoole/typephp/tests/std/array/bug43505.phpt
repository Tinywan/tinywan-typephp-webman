--TEST--
Bug #43505 (Assign by reference bug)
--INI--
error_reporting=0
--SKIPIF--
<?php
if (true) die("skip AOT does not support undefined variables");
?>

--FILE--
<?php
class Test implements Countable
{
    #[ReturnTypeWillChange]
    public function count()
    {
        return $some;
    }
}
function main()
{
    $obj = new Test();
    $a = array();
    $b =& $a['test'];
    var_dump($a);
    $t = count($obj);
    $a = array();
    $b =& $a['test'];
    var_dump($a);
}
?>
--EXPECT--
array(1) {
  ["test"]=>
  &NULL
}
array(1) {
  ["test"]=>
  &NULL
}
