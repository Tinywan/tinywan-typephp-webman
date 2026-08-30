--TEST--
refval
--FILE--
<?php
function main()
{
    eval('function retval_test(&$name) { $name .= "refval test"; }');

    $name = 'php ';
    retval_test(refval($name));
    echo $name;
}
?>
--EXPECT--
php refval test