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
    case 88: {
        echo "gt 88\n";
        break;
    }
    default:
    echo "default\n";
    break;
    }
}
?>
--EXPECT--
gt 88
