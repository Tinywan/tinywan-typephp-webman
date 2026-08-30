--TEST--
concat assignment treats null as empty string
--FILE--
<?php
function main(): void
{
    $s = null;
    $s .= 'a';
    $s .= 123;
    var_dump($s);

    $items = [];
    $items['x'] = null;
    $items['x'] .= 'b';
    var_dump($items['x']);
}
?>
--EXPECT--
string(4) "a123"
string(1) "b"
