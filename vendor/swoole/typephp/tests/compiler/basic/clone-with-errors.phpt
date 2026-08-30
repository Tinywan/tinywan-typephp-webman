--TEST--
PHP 8.5 clone-with stops property updates at the first error
--SKIPIF--
<?php
if (PHP_VERSION_ID < 80500) {
    die('skip requires PHP 8.5');
}
?>
--FILE--
<?php

class CloneWithErrors
{
    public int $value = 1;

    public function __set(string $name, mixed $value): void
    {
        echo $name, ':', $value, "\n";
        if ($name === 'stop') {
            throw new RuntimeException('rejected ' . $value);
        }
    }
}

function main(): void
{
    $source = new CloneWithErrors();

    try {
        clone($source, [
            'before' => 'first',
            'stop' => 'reject',
            'after' => 'last',
        ]);
    } catch (RuntimeException $error) {
        echo $error->getMessage(), "\n";
    }

    try {
        clone($source, ['value' => 'invalid']);
    } catch (TypeError $error) {
        echo $error::class, ":property\n";
    }

    try {
        clone($source, 42);
    } catch (TypeError $error) {
        echo $error::class, ":argument\n";
    }
}
?>
--EXPECT--
before:first
stop:reject
rejected reject
TypeError:property
TypeError:argument
