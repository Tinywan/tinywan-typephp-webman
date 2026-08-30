--TEST--
include path expression side effects and return value
--FILE--
<?php

function include_target(string $name): string
{
    echo "target:$name\n";
    return __DIR__ . '/' . $name;
}

function main(): void
{
    $ret = include include_target('test_include_return.inc');
    var_dump($ret);

    $ret = include_once include_target('test_include_return.inc');
    var_dump($ret);
}
?>
--EXPECT--
target:test_include_return.inc
included:test_include_return.inc
int(123)
target:test_include_return.inc
bool(true)
