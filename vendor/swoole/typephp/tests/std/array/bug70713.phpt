--TEST--
Bug #70713: Use After Free Vulnerability in array_walk()/array_walk_recursive()
--SKIPIF--
<?php die("skip AOT array_walk_recursive UAF differs from PHP"); ?>
--FILE--
<?php
class obj
{
    function __tostring(): string {
        global $arr;
        $arr = 1;
        for ($i = 0; $i < 5; $i++) {
            $v[$i] = 'hi' . $i;
        }
        return 'hi';
    }
}
function main()
{
    $arr = array('string' => new obj());
    try {
        array_walk_recursive($arr, 'settype');
    } catch (\TypeError $e) {
        echo $e->getMessage() . "\n";
    }
}
?>
--EXPECT--
Iterated value is no longer an array or object
