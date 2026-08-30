--TEST--
string_method: 0
--FILE--
<?php

function main()
{
    require __DIR__ . '/../../../src/Assert.php';

    $string = 'hello world, this is a test string';
    Assert::false($string->isEmpty());
    Assert::eq($string->length(), strlen($string));
    Assert::eq($string->substr(0, 5), 'hello');
    Assert::eq($string->contains('world'), true);
    Assert::eq($string->indexOf('test'), strpos($string, 'test'));
    Assert::eq($string->split(' '), explode(' ', $string));

    $empty = '';
    Assert::true($empty->isEmpty());
}
?>
--EXPECT--
