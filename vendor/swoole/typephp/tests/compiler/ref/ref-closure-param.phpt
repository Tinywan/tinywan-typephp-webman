--TEST--
closure function with ref parameter
--SKIPIF--
<?php die('skip'); ?>
--FILE--
<?php
//function main()
//{
//    $s = "foo";
//    $testFn = function (&$data) {
//        $data .= " bar";
//    };
//    $testFn($s);
//    var_dump($s);
//}
function main()
{
    $testFn = function (&$data) {
        $data .= " (_)";
    };
    $sweet = array('a' => 'apple', 'b' => 'banana');
    $fruits = array('sweet' => $sweet, 'sour' => 'lemon');

    array_walk_recursive($fruits, $testFn);
    var_dump($fruits);
}
?>
--EXPECT--
string(7) "foo bar"
