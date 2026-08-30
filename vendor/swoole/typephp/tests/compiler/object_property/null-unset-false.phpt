--TEST--
object property null and unset should both behave as false
--FILE--
<?php

class NullUnsetFalseBox
{
    public $value = 1;
}

function main() {
    $box = new NullUnsetFalseBox();

    var_dump((bool) $box->value);

    $box->value = null;
    var_dump((bool) $box->value);
    var_dump(isset($box->value));
    var_dump(empty($box->value));

    $box->value = 1;
    unset($box->value);
    var_dump(isset($box->value));
    var_dump(empty($box->value));
}
?>
--EXPECT--
bool(true)
bool(false)
bool(false)
bool(true)
bool(false)
bool(true)
