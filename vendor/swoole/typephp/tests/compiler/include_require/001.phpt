--TEST--
include/require unwind safely when ZendVM calls a compiled function that throws
--ENV--
PHPRC=tests/compiler/include_require/no-leak.ini
PHP_INI_SCAN_DIR={PWD}/empty-ini-dir
--FILE--
<?php
declare(strict_types=1);
function Hello(string $value) {}

function includePath(string $name): string
{
    return implode('', [__DIR__, '/', ucfirst(strtolower($name)), '.php']);
}

function main(): void
{
    // Keep this name: generated helper variables must not collide with PHP locals.
    $filename = includePath('hello');

    try {
        include $filename;
    } catch (ArgumentCountError $e) {
        echo "include ArgumentCountError\n";
    }

    try {
        require includePath('hello');
    } catch (ArgumentCountError $e) {
        echo "require ArgumentCountError\n";
    }
}
?>
--EXPECT--
include ArgumentCountError
require ArgumentCountError
