--TEST--
string_method: json
--FILE--
<?php

function main()
{
    require __DIR__ . '/../../../src/Assert.php';

    $array = ['a' => random_bytes(128)->base64Encode(), 'b' => random_int(1, PHP_INT_MAX), 'c' => php_uname()];

    $str = $array->jsonEncode();
    Assert::notEmpty($str);
    Assert::eq($str->jsonDecode(), $array);
}
?>
--EXPECT--
