--TEST--
string_method: 1
--FILE--
<?php

function main()
{
    require __DIR__ . '/../../../src/Assert.php';

    $str = "first=value&arr[]=foo+bar&arr[]=baz";

    $output = $str->parseStr();
    Assert::eq($output['first'], 'value');
    Assert::eq($output['arr'][0], 'foo bar');
    Assert::eq($output['arr'][1], 'baz');
}
?>
--EXPECT--
