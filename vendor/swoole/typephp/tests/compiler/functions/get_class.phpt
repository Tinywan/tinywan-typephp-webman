--TEST--
class const 001
--FILE--
<?php
class Worker
{
    public const BAR = "string constant";
}

function main()
{
    $o = new Worker;
    var_dump(get_class($o));
}
?>
--EXPECT--
string(6) "Worker"