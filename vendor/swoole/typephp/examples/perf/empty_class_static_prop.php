<?php
use native_types;
class Foo {
    static public int $a;
}

function main() {
    $s = microtime( true);
    $n = 1000_0000;
    for ($i = 0; $i < $n; ++$i) {
        $x = empty(Foo::$a);
    }
    echo microtime( true) - $s, "\n";
}