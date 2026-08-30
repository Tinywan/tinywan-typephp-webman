--TEST--
negative string offset read
--FILE--
<?php

function format_kind(string $format): string
{
    if ($format[0] == '.' and $format[-1] == 'f') {
        return 'float:' . substr($format, 1, strlen($format) - 2);
    } elseif (str_contains($format, '%')) {
        return 'date:' . $format;
    }
    return 'other:' . $format[-1];
}

function main(): void
{
    var_dump(format_kind('.3f'));
    var_dump(format_kind('%Y-%m-%d'));
    var_dump(format_kind('abc'));
}
?>
--EXPECT--
string(7) "float:3"
string(13) "date:%Y-%m-%d"
string(7) "other:c"
