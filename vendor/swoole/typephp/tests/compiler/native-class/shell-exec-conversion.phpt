--TEST--
Native class: shell command interpolation uses the declared toString method
--SKIPIF--
<?php
if (PHP_OS_FAMILY === 'Windows') {
    die('skip shell command is POSIX-specific');
}
?>
--FILE--
<?php

#[Native]
class NativeShellCommand
{
    public function toString(): string
    {
        return 'printf native-shell';
    }
}

function main(): void
{
    $command = new NativeShellCommand();
    echo `$command`, "\n";
}

?>
--EXPECT--
native-shell
