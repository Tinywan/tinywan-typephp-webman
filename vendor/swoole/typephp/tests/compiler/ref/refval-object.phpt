--TEST--
refval with object property
--FILE--
<?php
function main()
{
    eval('function prop_ref_test(&$val) { $val = "modified"; }');

    $obj = new stdClass();
    $obj->prop = 'original';
    prop_ref_test(refval($obj->prop));
    echo $obj->prop;
}
?>
--EXPECT--
modified
