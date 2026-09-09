--TEST--
std::ref with a variable
--FILE--
<?php
function main()
{
    eval('function reference_test(&$name) { $name .= "std::ref test"; }');

    $name = std::any('php ');
    reference_test(std::ref($name));
    echo $name;
}
?>
--EXPECT--
php std::ref test
