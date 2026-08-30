--TEST--
Named Arguments - PHP 8+ function call syntax
--FILE--
<?php
class Foo {
    public function __construct(string $a, int $b, float $c) {
        var_dump($a, $b, $c);
    }
}

function main() {
    $o = new Foo(b: 2026, c: 3.1415, a: "[content]");
}
?>
--EXPECT--
string(9) "[content]"
int(2026)
float(3.1415)
