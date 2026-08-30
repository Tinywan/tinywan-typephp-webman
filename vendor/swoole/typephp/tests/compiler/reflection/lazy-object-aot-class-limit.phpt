--TEST--
PHP 8.4 lazy objects reject persistent AOT class entries safely
--SKIPIF--
<?php
if (PHP_VERSION_ID < 80400) {
    die('skip requires PHP 8.4');
}
?>
--FILE--
<?php

final class AotLazyObjectTarget
{
    public string $name;
}

function main(): void
{
    $reflection = new ReflectionClass(AotLazyObjectTarget::class);
    try {
        $reflection->newLazyGhost(function (AotLazyObjectTarget $target): void {
            $target->name = 'loaded';
        });
    } catch (Error $error) {
        echo $error->getMessage(), "\n";
    }
}
?>
--EXPECT--
Cannot make instance of internal class lazy: AotLazyObjectTarget is internal
