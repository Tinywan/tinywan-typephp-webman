--TEST--
string item isset
--FILE--
<?php
function main()
{
    $type = '?string';
    var_dump(isset($type[0]));
    var_dump(isset($type[6]));
    var_dump(isset($type[10]));
    var_dump(($type[0] ?? ''));
}
?>
--EXPECT--
bool(true)
bool(true)
bool(false)
string(1) "?"
