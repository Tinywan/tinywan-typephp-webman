--TEST--
string_method: marshal
--FILE--
<?php

function main()
{
    require __DIR__ . '/../../../src/Assert.php';

    $array = [];
    $array->set('a', 'marshal_test_value');
    $array->set('b', 100);
    $array->set('c', 'system_info');

    $str = $array->marshal();
    Assert::notEmpty($str);
    Assert::eq($str->unmarshal(), $array);
}
?>
--EXPECT--
