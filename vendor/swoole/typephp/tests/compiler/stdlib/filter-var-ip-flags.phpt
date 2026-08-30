--TEST--
filter_var IP validation with strict false checks and flags
--FILE--
<?php

function classify_ip(string $value): string
{
    if (false === filter_var($value, FILTER_VALIDATE_IP)) {
        return 'invalid';
    }
    if (false !== filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return 'ipv4';
    }
    if (false !== filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return 'ipv6';
    }
    return 'unknown';
}

function main(): void
{
    var_dump(classify_ip('127.0.0.1'));
    var_dump(classify_ip('2001:db8::1'));
    var_dump(classify_ip('not-an-ip'));
}
?>
--EXPECT--
string(4) "ipv4"
string(4) "ipv6"
string(7) "invalid"
