--TEST--
Compound assignment in expressions
--FILE--
<?php

class Counter {
    public int $val = 0;
}

function main() {
    // Array dim fetch with assignment operators
    $arr = [1, 2, 3];
    $arr[0] += 10;
    $arr[1] *= 3;
    $arr[2] -= 1;
    var_dump($arr);

    // Property with assignment operators
    $obj = new Counter();
    $obj->val += 5;
    $obj->val *= 2;
    $obj->val -= 3;
    $obj->val %= 4;
    var_dump($obj->val);

    // Chained assignments
    $a = $b = $c = 10;
    var_dump($a);
    var_dump($b);
    var_dump($c);

    // Reference assignment
    $y = 2;
    $z = &$y;
    $y = 5;
    var_dump($z);

    // Global variable with assignment
    global $config;
    $config = ["debug" => true];
    var_dump($config["debug"]);

    echo "done\n";
}

?>
--EXPECT--
array(3) {
  [0]=>
  int(11)
  [1]=>
  int(6)
  [2]=>
  int(2)
}
int(3)
int(10)
int(10)
int(10)
int(5)
bool(true)
done
