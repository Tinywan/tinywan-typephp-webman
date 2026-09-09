--TEST--
std::ref with an object property
--FILE--
<?php
function main()
{
    eval('function prop_ref_test(&$val) { $val = "modified"; }');

    $obj = new stdClass();
    $obj->prop = 'original';
    prop_ref_test(std::ref($obj->prop));
    echo $obj->prop;
}
?>
--EXPECT--
modified
