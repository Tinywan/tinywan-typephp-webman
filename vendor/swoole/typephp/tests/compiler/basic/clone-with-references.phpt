--TEST--
PHP 8.5 clone-with rejects active references and unwraps a sole remaining reference
--SKIPIF--
<?php
if (PHP_VERSION_ID < 80500) {
    die('skip requires PHP 8.5');
}
?>
--FILE--
<?php

function main(): void
{
    $source = new stdClass();
    $value = 'reference';
    $updates = ['value' => &$value];

    try {
        clone($source, $updates);
    } catch (Error $error) {
        echo $error->getMessage(), "\n";
    }

    unset($value);
    $copy = clone($source, $updates);
    var_dump($copy->value);
}
?>
--EXPECT--
Cannot assign by reference when cloning with updated properties
string(9) "reference"
