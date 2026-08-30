--TEST--
refval
--FILE--
<?php
function retval_test(&$name) {
    $name .= "refval test";
}
function main()
{
    $name = 'php ';
    retval_test(refval($name));
    echo $name;
}
?>
--EXPECT--
php refval test