--TEST--
class static 005
--FILE--
<?php
class Worker
{
    final public const VERSION = '5.1.9';

    static function init()
    {
        var_dump(static::VERSION);
    }
}

function main()
{
    Worker::init();
}
?>
--EXPECT--
string(5) "5.1.9"
