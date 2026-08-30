--TEST--
foreach with key-value and nested structures
--FILE--
<?php

function main() {
    // Simple key-value foreach
    $arr = ["a" => 1, "b" => 2, "c" => 3];
    $keys = [];
    $vals = [];
    foreach ($arr as $k => $v) {
        $keys[] = $k;
        $vals[] = $v;
    }
    var_dump($keys);
    var_dump($vals);

    // Nested foreach
    $matrix = [[1, 2], [3, 4]];
    $sum = 0;
    foreach ($matrix as $row) {
        foreach ($row as $val) {
            $sum += $val;
        }
    }
    var_dump($sum);

    // Foreach on array literal
    $count = 0;
    foreach ([10, 20, 30] as $item) {
        $count += $item;
    }
    var_dump($count);

    echo "done\n";
}

?>
--EXPECT--
array(3) {
  [0]=>
  string(1) "a"
  [1]=>
  string(1) "b"
  [2]=>
  string(1) "c"
}
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
}
int(10)
int(60)
done
