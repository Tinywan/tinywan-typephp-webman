--TEST--
Bug #30266 (Invalid opcode 137/1/8) and array_walk
--SKIPIF--
<?php die("skip AOT array_walk behavior differs from PHP"); ?>
--FILE--
<?php
class testc
{
    public $b = "c";
    function crash($val)
    {
        $this->b = $val;
        throw new Exception("Error");
    }
}
function test($item2, $key, $userd)
{
    $userd->crash($item2);
}
function main()
{
    $fruits = array("d" => "lemon", "a" => "orange", "b" => "banana", "c" => "apple");
    $myobj = new testc();
    try {
        array_walk($fruits, 'test', $myobj);
    } catch (Exception $e) {
        echo "Caught: " . $e->getMessage() . "\n";
    }
}
?>
--EXPECT--
Caught: Error
