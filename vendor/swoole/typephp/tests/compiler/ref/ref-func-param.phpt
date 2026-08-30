--TEST--
Ref function parameter
--FILE--
<?php
function test_fn(&$data)
{
    $data .= " bar";
}

function main()
{
    $s = "foo";
    test_fn($s);
    var_dump($s);
}
?>
--EXPECT--
string(7) "foo bar"
