--TEST--
isset with multiple arguments and edge cases
--FILE--
<?php

function main() {
    $a = "hello";
    $b = null;
    $c = 0;

    // Single isset
    var_dump(isset($a));
    var_dump(isset($b));
    var_dump(isset($c));

    // Multi-arg isset
    var_dump(isset($a, $b));
    var_dump(isset($a, $c));

    // Array isset
    $arr = ["x" => 1, "y" => null];
    var_dump(isset($arr["x"]));
    var_dump(isset($arr["y"]));
    var_dump(isset($arr["z"]));

    // Nested isset
    $data = ["user" => ["name" => "Alice"]];
    var_dump(isset($data["user"]["name"]));
    var_dump(isset($data["user"]["email"]));
    var_dump(isset($data["missing"]["key"]));

    echo "done\n";
}

?>
--EXPECT--
bool(true)
bool(false)
bool(true)
bool(false)
bool(true)
bool(true)
bool(false)
bool(false)
bool(true)
bool(false)
bool(false)
done
