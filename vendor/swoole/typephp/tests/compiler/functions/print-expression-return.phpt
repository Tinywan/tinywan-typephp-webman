--TEST--
print expression returns 1 and can be composed
--FILE--
<?php

function main(): void
{
    $ret = print "hello\n";
    var_dump($ret);

    if (print "cond\n") {
        echo "branch\n";
    }
}
?>
--EXPECT--
hello
int(1)
cond
branch
