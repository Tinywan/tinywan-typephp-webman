--TEST--
Intersection type: return runtime type checking
--FILE--
<?php

interface IA {}
interface IB {}

class Both implements IA, IB {}
class OnlyA implements IA {}

function return_both(object $value): IA&IB {
    return $value;
}

function main() {
    var_dump(get_class(return_both(new Both())));

    $errors = [];

    try {
        return_both(new OnlyA());
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
string(63) "return_both(): Return value must be of type IA&IB, object given"
