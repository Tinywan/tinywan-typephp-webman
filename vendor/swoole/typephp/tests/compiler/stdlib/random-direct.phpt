--TEST--
random functions use direct wrappers and preserve PHP range semantics
--FILE--
<?php

function main(): void
{
    $mt = mt_rand();
    var_dump($mt >= 0 && $mt <= mt_getrandmax());

    $mtRange = mt_rand(10, 20);
    var_dump($mtRange >= 10 && $mtRange <= 20);

    $randRange = rand(20, 10);
    var_dump($randRange >= 10 && $randRange <= 20);

    $secure = random_int(5, 5);
    var_dump($secure);
    var_dump(strlen(random_bytes(8)));
    var_dump(mt_getrandmax());
    var_dump(getrandmax());

    try {
        mt_rand(20, 10);
    } catch (ValueError $error) {
        echo $error->getMessage(), "\n";
    }

    try {
        random_int(20, 10);
    } catch (ValueError $error) {
        echo $error->getMessage(), "\n";
    }
}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
int(5)
int(8)
int(2147483647)
int(2147483647)
mt_rand(): Argument #2 ($max) must be greater than or equal to argument #1 ($min)
random_int(): Argument #1 ($min) must be less than or equal to argument #2 ($max)
