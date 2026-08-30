--TEST--
global vars (002)
--FILE--
<?php
function foo()
{
    global $gv;
    $gv = 100;
}

function main()
{
    var_dump(is_array($_SERVER));
    var_dump(is_array($GLOBALS['_SERVER']));
    foo();

    global $gv;
    var_dump($gv);

    global $gv;
    var_dump($gv);
}

?>
--EXPECTF--
bool(true)
bool(true)
int(100)
int(100)