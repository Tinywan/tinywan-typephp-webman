--TEST--
shell execution operator returns command output
--FILE--
<?php

function main(): void
{
    $name = 'aot';
    $out = `printf "hello-%s" $name`;
    var_dump($out);
}
?>
--EXPECT--
string(9) "hello-aot"
