--TEST--
camel case to snake case with regex replacement
--FILE--
<?php

function camel_to_snake(string $name): string
{
    return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name));
}

function main(): void
{
    var_dump(camel_to_snake('toStdOrderedMap'));
    var_dump(camel_to_snake('HTMLParserValue'));
    var_dump(camel_to_snake('simple'));
}
?>
--EXPECT--
string(18) "to_std_ordered_map"
string(16) "htmlparser_value"
string(6) "simple"
