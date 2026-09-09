--TEST--
ctype functions use direct C classification without ext-ctype
--FILE--
<?php

function classifyDigit(mixed $value): bool
{
    return ctype_digit($value);
}

function main(): void
{
    var_dump(ctype_alnum('Abc123'));
    var_dump(ctype_alpha('AbC'));
    var_dump(ctype_cntrl("\n"));
    var_dump(classifyDigit('0123'));
    var_dump(ctype_lower('abc'));
    var_dump(ctype_graph('!A1'));
    var_dump(ctype_print(' A1!'));
    var_dump(ctype_punct('!?'));
    var_dump(ctype_space(" \t\n"));
    var_dump(ctype_upper('ABC'));
    var_dump(ctype_xdigit(text: 'A09f'));

    var_dump(ctype_digit('12a'));
    var_dump(ctype_alpha('abc1'));
    var_dump(ctype_space(" \tX"));
    var_dump(ctype_print("\n"));
    var_dump(ctype_digit(''));

    // Preserve ext/ctype's legacy integer classification without calling its
    // zif handlers. Other non-string values simply classify as false.
    var_dump(ctype_digit(49));
    var_dump(ctype_alpha(65));
    var_dump(ctype_digit(1000));
    var_dump(ctype_alpha(1000));
    var_dump(ctype_print(-1000));
    var_dump(ctype_digit(null));
    var_dump(ctype_digit(false));
    var_dump(ctype_digit([]));
}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(false)
bool(false)
bool(false)
bool(false)
bool(false)
bool(true)
bool(true)
bool(true)
bool(false)
bool(true)
bool(false)
bool(false)
bool(false)
