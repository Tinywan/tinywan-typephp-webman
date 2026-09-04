--TEST--
closure function with ref parameter
--FILE--
<?php
function main()
{
    $testFn = function (&$data, $key) {
        $data .= " (_)";
    };
    $sweet = array('a' => 'apple', 'b' => 'banana');
    $fruits = array('sweet' => $sweet, 'sour' => 'lemon');

    array_walk_recursive($fruits, $testFn);
    var_dump($fruits);
}
?>
--EXPECT--
array(2) {
  ["sweet"]=>
  array(2) {
    ["a"]=>
    string(9) "apple (_)"
    ["b"]=>
    string(10) "banana (_)"
  }
  ["sour"]=>
  string(9) "lemon (_)"
}
