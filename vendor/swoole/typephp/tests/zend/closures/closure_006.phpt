--TEST--
Closure 006: Nested lambdas
--FILE--
<?php

$getClosure = function ($v) {
    return function () use ($v) {
        echo "Hello World: $v!\n";
        return null;
    };
};

$closure = $getClosure (2);
$closure ();

echo "Done\n";
?>
--EXPECT--
Hello World: 2!
Done
