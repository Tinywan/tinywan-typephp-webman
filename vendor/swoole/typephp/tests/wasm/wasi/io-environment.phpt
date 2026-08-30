--TEST--
WASI provides arguments, environment variables and standard input
--ARGS--
alpha "two words"
--ENV--
TYPEPHP_WASM_GREETING=hello-wasi
--STDIN--
input from host
--FILE--
<?php
function main(): void
{
    global $argv;
    echo implode('|', array_slice($argv, 1)), "\n";
    echo getenv('TYPEPHP_WASM_GREETING'), "\n";
    echo trim(stream_get_contents(STDIN)), "\n";
}
?>
--EXPECT--
alpha|two words
hello-wasi
input from host
