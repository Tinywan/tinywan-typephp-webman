--TEST--
switch
--FILE--
<?php
function test($value) {
    switch ($value) {
    case 100:
        echo "gt 100\n";
        break;
    case 88: {
        echo "gt 88\n";
        break;
    }
    default:
        echo "default\n";
        break;
    }
}
function main()
{
    test(100);
    test(88);
    test(77);
}
?>
--EXPECT--
gt 100
gt 88
default
