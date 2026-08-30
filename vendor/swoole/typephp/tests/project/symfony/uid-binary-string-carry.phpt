--TEST--
Symfony pattern: binary string offset mutation with ord/chr carry
--FILE--
<?php

final class BinaryUtil
{
    public static function add(string $a, string $b): string
    {
        $carry = 0;
        for ($i = 7; 0 <= $i; --$i) {
            $carry += ord($a[$i]) + ord($b[$i]);
            $a[$i] = chr($carry & 0xFF);
            $carry >>= 8;
        }

        return $a;
    }
}

function main(): void
{
    var_dump(bin2hex(BinaryUtil::add(hex2bin('00000000000000ff'), hex2bin('0000000000000001'))));
    var_dump(bin2hex(BinaryUtil::add(hex2bin('ffffffffffffffff'), hex2bin('0000000000000001'))));
}
?>
--EXPECT--
string(16) "0000000000000100"
string(16) "0000000000000000"
