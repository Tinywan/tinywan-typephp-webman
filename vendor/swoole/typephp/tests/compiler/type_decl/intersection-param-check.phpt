--TEST--
Intersection type: parameter runtime type checking
--FILE--
<?php

interface IA {}
interface IB {}

class Both implements IA, IB {}
class OnlyA implements IA {}

function expect_both(IA&IB $value): void {
    var_dump(get_class($value));
}

function main() {
    expect_both(new Both());

    $errors = [];

    try {
        expect_both(any(new OnlyA()));
    } catch (\TypeError $e) {
        $errors[] = $e->getMessage();
    }

    foreach ($errors as $err) {
        var_dump($err);
    }
}
?>
--EXPECT--
string(4) "Both"
string(71) "expect_both(): Argument #1 ($value) must be of type IA&IB, object given"
