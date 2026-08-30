--TEST--
Type casts: (int), (float), (string), (bool)
--FILE--
<?php

function main() {
    // Integer cast
    var_dump((int) "123");
    var_dump((int) 3.14);

    // Float cast
    var_dump((float) "3.14");
    var_dump((float) 10);

    // Bool cast with literals — triggers toBool(0L)/toBool(1L) code path
    var_dump((bool) 1);
    var_dump((bool) 0);
    var_dump((bool) "hello");
    var_dump((bool) "");

    // String cast
    var_dump((string) 123);
    var_dump((string) true);
    var_dump((string) false);

    // Bool cast with variables
    $one = 1;
    $zero = 0;
    var_dump((bool) $one);
    var_dump((bool) $zero);

    // Nested casts
    $val = (int) (string) (float) "5.7";
    var_dump($val);

    echo "done\n";
}

?>
--EXPECT--
int(123)
int(3)
float(3.14)
float(10)
bool(true)
bool(false)
bool(true)
bool(false)
string(3) "123"
string(1) "1"
string(0) ""
bool(true)
bool(false)
int(5)
done
