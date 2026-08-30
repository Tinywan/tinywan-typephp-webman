--TEST--
refval with array element
--FILE--
<?php
function main()
{
    eval('function array_ref_test(&$val) { $val = "modified"; }');

    $arr = ['key' => 'original'];
    array_ref_test(refval($arr['key']));
    echo $arr['key'];
}
?>
--EXPECT--
modified
