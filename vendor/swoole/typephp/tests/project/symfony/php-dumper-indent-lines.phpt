--TEST--
Symfony DI pattern: indent generated code lines with explode array_map implode
--FILE--
<?php

function indentCode(string $code): string
{
    return implode("\n", array_map(static fn ($line) => $line ? '    '.$line : $line, explode("\n", $code)));
}

function main(): void
{
    var_dump(indentCode("first\n\nsecond"));
}
?>
--EXPECT--
string(21) "    first

    second"
