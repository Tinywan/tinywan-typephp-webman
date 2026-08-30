--TEST--
Type Declarations
--FILE--
<?php
class Foo12 {
    public function foo(): string {
        $arr = [1, 2, 3, 4, 5];
        return count($arr);
    }
}

function main() {
    $o = new Foo12;
    var_dump($o->foo());
}
?>
--EXPECT--
string(1) "5"
