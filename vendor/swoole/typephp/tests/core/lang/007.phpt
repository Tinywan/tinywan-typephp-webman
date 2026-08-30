--TEST--
Function call with global and static variables
--FILE--
<?php
function Test()
{
    static $a=1;
    global $b;
    $c = 1;
    $b = 5;
    echo "$a $b $c\n";
    $a++;
    $c++;
    echo "$a $b $c\n";
}

function main() {
    error_reporting(0);
    Test();
    Test();
    Test();
}
?>
--EXPECT--
1 5 1
2 5 2
2 5 1
3 5 2
3 5 1
4 5 2