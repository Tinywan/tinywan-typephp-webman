--TEST--
Concat empty strings
--FILE--
<?php
function main() {
    var_dump('' . 1 . '' . 'a' . '');
    var_dump('' . 1);
    var_dump(1 . '');
    var_dump(false . '');
    var_dump('' . '' . '');

    $value = 'value';
    var_dump('' . $value . '');
    var_dump('' . get_value() . '');

    $number = 1;
    $number .= '';
    var_dump($number);
}

function get_value(): string {
    return 'called';
}
?>
--EXPECT--
string(2) "1a"
string(1) "1"
string(1) "1"
string(0) ""
string(0) ""
string(5) "value"
string(6) "called"
string(1) "1"
