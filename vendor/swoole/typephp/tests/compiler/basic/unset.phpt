--TEST--
unset on arrays and variables
--FILE--
<?php

function main() {
    // Unset variable
    $a = 10;
    unset($a);
    var_dump(isset($a));

    // Unset array element
    $arr = ["x" => 1, "y" => 2, "z" => 3];
    unset($arr["y"]);
    var_dump(count($arr));
    var_dump(isset($arr["y"]));
    var_dump($arr["x"]);
    var_dump($arr["z"]);

    // Unset last element
    $arr2 = [1, 2, 3, 4];
    unset($arr2[3]);
    var_dump(count($arr2));

    // Unset with variable key
    $items = ["a" => 1, "b" => 2, "c" => 3];
    $key = "b";
    unset($items[$key]);
    var_dump(count($items));
    var_dump(isset($items["b"]));

    echo "done\n";
}

?>
--EXPECT--
bool(false)
int(2)
bool(false)
int(1)
int(3)
int(3)
int(2)
bool(false)
done
