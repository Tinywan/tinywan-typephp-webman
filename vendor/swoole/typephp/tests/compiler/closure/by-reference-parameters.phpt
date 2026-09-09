--TEST--
Dynamic Closures accept positional arguments explicitly marked with std::ref
--FILE--
<?php

function main(): void
{
    $fixed = static function (&$value): void {
        $value .= '!';
    };
    $text = std::any('fixed');
    $fixed(std::ref($text));
    var_dump($text);

    $optional = static function (&$value = null): void {
        var_dump($value);
        $value = 'private-default';
    };
    $optional();

    $arrow = static fn (&$value): int => ++$value;
    $number = std::any(40);
    var_dump($arrow(std::ref($number)), $number);

    $typed = static function (int &$value): void {
        $value++;
    };
    $typed(std::ref($number));
    var_dump($number);

    $invalid = std::any('not-an-int');
    try {
        $typed(std::ref($invalid));
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
