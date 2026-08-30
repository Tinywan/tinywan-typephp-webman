--TEST--
known class entries can be reused across repeated object creation
--FILE--
<?php

class RepeatedObject
{
    public int $value = 7;
}

function main(): void
{
    $last = null;
    for ($i = 0; $i < 3; ++$i) {
        $last = new RepeatedObject();
    }
    var_dump($last->value);
}
?>
--EXPECT--
int(7)
