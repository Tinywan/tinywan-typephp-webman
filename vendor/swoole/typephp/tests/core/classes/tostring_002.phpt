--TEST--
ZE2 __toString() in __destruct
--FILE--
<?php

class Test
{
    function __toString(): string
    {
        return "Hello\n";
    }

    function __destruct()
    {
        echo $this;
    }
}

function main() {
    $o = new Test;
    $o = new Test;
}
?>
--EXPECT--
Hello
Hello
