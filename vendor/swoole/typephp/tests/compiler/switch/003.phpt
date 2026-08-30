--TEST--
switch
--FILE--
<?php
function main()
{
    $c = 100;
    switch ($c) {
    case 100:
    case 99:
    default:
        echo "default\n";
        break;
    }
}
?>
--EXPECT--
default
