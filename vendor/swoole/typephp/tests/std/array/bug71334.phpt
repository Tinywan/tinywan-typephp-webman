--TEST--
Bug #71334: Cannot access array keys while uksort()
--SKIPIF--
--FILE--
<?php
class myClass
{
    private $a = ['foo-test' => [1], '-' => [2], 'bar-test' => [3]];
    public function _mySort($x, $y)
    {
        if (!isset($this->a[$x])) {
            throw new Exception('Missing X: "' . $x . '"');
        }
        if (!isset($this->a[$y])) {
            throw new Exception('Missing Y: "' . $y . '"');
        }
        return $x <=> $y;
    }
    public function __construct()
    {
        uksort($this->a, [$this, '_mySort']);
    }
}
function main()
{
    new myClass();
    echo "Done";
}
?>
--EXPECT--
Done
