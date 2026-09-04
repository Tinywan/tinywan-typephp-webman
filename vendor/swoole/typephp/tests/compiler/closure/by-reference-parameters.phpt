--TEST--
Dynamic Closures accept positional arguments explicitly marked with refval
--FILE--
<?php

function main(): void
{
    $fixed = static function (&$value): void {
        $value .= '!';
    };
    $text = 'fixed';
    $fixed(refval($text));
    var_dump($text);

    $optional = static function (&$value = null): void {
        var_dump($value);
        $value = 'private-default';
    };
    $optional();

    $arrow = static fn (&$value): int => ++$value;
    $number = 40;
    var_dump($arrow(refval($number)), $number);

    $typed = static function (int &$value): void {
        $value++;
    };
    $typed(refval($number));
    var_dump($number);

    $invalid = any('not-an-int');
    try {
        $typed(refval($invalid));
    } catch (TypeError $error) {
        echo "typed reference rejected\n";
    }
}
?>
--EXPECT--
string(6) "fixed!"
NULL
int(41)
int(41)
int(42)
typed reference rejected
