--TEST--
Symfony CssSelector pattern: unicode escape preg_replace_callback with bit operations
--FILE--
<?php
function cssUnicodeUnescape(string $value): string
{
    return preg_replace_callback('/\\\\([0-9a-fA-F]{1,6})\s?/', static function ($match) {
        $c = hexdec($match[1]);

        if (0x80 > $c %= 0x200000) {
            return chr($c);
        }
        if (0x800 > $c) {
            return chr(0xC0 | $c >> 6).chr(0x80 | $c & 0x3F);
        }
        if (0x10000 > $c) {
            return chr(0xE0 | $c >> 12).chr(0x80 | $c >> 6 & 0x3F).chr(0x80 | $c & 0x3F);
        }

        return '';
    }, $value);
}

function main(): void
{
    var_dump(cssUnicodeUnescape('\\41 \\26'));
    var_dump(bin2hex(cssUnicodeUnescape('\\20ac')));
}
?>
--EXPECT--
string(2) "A&"
string(6) "e282ac"
