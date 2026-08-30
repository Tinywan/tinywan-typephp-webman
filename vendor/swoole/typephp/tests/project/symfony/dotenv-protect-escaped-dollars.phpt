--TEST--
Symfony Dotenv pattern: preg_replace_callback preserves escaped dollars by backslash parity
--FILE--
<?php
function protectEscapedDollars(string $value): string
{
    if (!str_contains($value, '$')) {
        return $value;
    }

    return preg_replace_callback('/\\\\+\$/', static function ($m) {
        $bs = substr($m[0], 0, -1);
        if (1 === strlen($bs) % 2) {
            return substr($bs, 0, -1)."\x00";
        }

        return $m[0];
    }, $value);
}

function main(): void
{
    var_dump(bin2hex(protectEscapedDollars('A\\$B')));
    var_dump(protectEscapedDollars('A\\\\$B'));
    var_dump(protectEscapedDollars('plain'));
}
?>
--EXPECT--
string(6) "410042"
string(5) "A\\$B"
string(5) "plain"
