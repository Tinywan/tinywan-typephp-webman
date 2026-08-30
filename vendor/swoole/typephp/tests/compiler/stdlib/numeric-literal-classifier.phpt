--TEST--
numeric literal classifier style regex and digit extraction
--FILE--
<?php

function strip_numeric_underscores(string $value): string
{
    return str_replace('_', '', $value);
}

function is_big_int_literal_string(string $rawValue): bool
{
    $clean = strip_numeric_underscores($rawValue);
    if (!preg_match('/^\d+$/', $clean)) {
        return false;
    }
    return strlen(ltrim($clean, '0')) >= 19;
}

function is_decimal_literal_string(string $rawValue): bool
{
    $clean = strip_numeric_underscores($rawValue);
    if (!preg_match('/[\.eE]/', $clean)) {
        return false;
    }
    $digits = preg_replace('/[^0-9]/', '', $clean);
    return strlen(ltrim($digits, '0')) >= 16;
}

function main(): void
{
    var_dump(is_big_int_literal_string('9_223_372_036_854_775_808'));
    var_dump(is_big_int_literal_string('0x10000000000000000'));
    var_dump(is_decimal_literal_string('123_456_789_012_345.6'));
    var_dump(is_decimal_literal_string('123.45'));
}
?>
--EXPECT--
bool(true)
bool(false)
bool(true)
bool(false)
