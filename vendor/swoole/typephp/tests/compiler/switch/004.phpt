--TEST--
switch
--FILE--
<?php
function foo($c)
{
    switch ($c) {
    case 1:
    case 2:
        echo "case 1\n";
        break;
    case 100:
    case 99:
    default:
        echo "default\n";
        break;
    }
}

function main() {
    foo(1);
    foo(100);
}
?>
--EXPECT--
case 1
default
