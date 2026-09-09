--TEST--
std::ref with a statically resolved function call
--FILE--
<?php
function reference_test(&$name) {
    $name .= "std::ref test";
}
function main()
{
    $name = std::any('php ');
    reference_test(std::ref($name));
    echo $name;
}
?>
--EXPECT--
php std::ref test
