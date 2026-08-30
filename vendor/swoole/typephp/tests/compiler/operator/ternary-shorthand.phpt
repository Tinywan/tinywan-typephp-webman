--TEST--
Ternary shorthand operator ?:
--FILE--
<?php

function main() {
    // Basic ternary shorthand
    $a = "hello";
    $result1 = $a ?: "default";
    var_dump($result1);

    // Falsy value with shorthand
    $b = "";
    $result2 = $b ?: "empty";
    var_dump($result2);

    // Zero with shorthand
    $c = 0;
    $result3 = $c ?: 42;
    var_dump($result3);

    // Null with shorthand
    $result4 = null ?: "fallback";
    var_dump($result4);

    // Chained shorthand
    $val = null;
    $result5 = $val ?: false ?: "final";
    var_dump($result5);

    // Array item with shorthand
    $arr = ["key" => "present"];
    $result6 = $arr["missing"] ?: "not found";
    var_dump($result6);

    $result7 = $arr["key"] ?: "not found";
    var_dump($result7);

    // Nested ternary shorthand
    $x = "";
    $y = "hello";
    $result8 = $x ?: ($y ?: "neither");
    var_dump($result8);

    echo "done\n";
}

?>
--EXPECT--
string(5) "hello"
string(5) "empty"
int(42)
string(8) "fallback"
string(5) "final"
string(9) "not found"
string(7) "present"
string(5) "hello"
done
