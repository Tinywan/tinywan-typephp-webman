--TEST--
parse_str() rejects null bytes
--SKIPIF--
<?php
echo 'skip parse_str() null byte ValueError is not implemented in AOT';
?>
--FILE--
<?php

try {
    parse_str("a=1\0&b=2", $result);
} catch (ValueError $e) {
    echo $e->getMessage(), "\n";
}

?>
--EXPECT--
parse_str(): Argument #1 ($string) must not contain any null bytes
