--TEST--
WASI provides wall clock and secure random sources
--FILE--
<?php
function main(): void
{
    $timestamp = time();
    $random = random_int(100, 200);
    var_dump($timestamp > 1700000000);
    var_dump(strtotime(date(DATE_ATOM, $timestamp)) === $timestamp);
    var_dump($random >= 100 && $random <= 200);
    var_dump(strlen(random_bytes(16)) === 16);
}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
