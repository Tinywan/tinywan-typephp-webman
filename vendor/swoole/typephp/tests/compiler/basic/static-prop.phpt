--TEST--
class static
--FILE--
<?php
class Worker
{
    public static string $hello;
}
function main()
{
    Worker::$hello = "hello";
    var_dump(Worker::$hello);
}
?>
--EXPECT--
string(5) "hello"