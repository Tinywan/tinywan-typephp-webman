--TEST--
WASM runtime exposes the WASI platform
--FILE--
<?php
function main(): void
{
    var_dump(PHP_SAPI === 'cli');
    var_dump(PHP_VERSION_ID >= 80400);
    var_dump(str_contains(php_uname(), 'wasm32'));
}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
