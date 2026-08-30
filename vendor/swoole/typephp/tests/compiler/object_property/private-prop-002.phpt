--TEST--
Static Class Property Read/Write Test
--FILE--
<?php
class Worker
{
    protected static function checkErrors(): void
    {
        var_dump(__METHOD__);
    }

    public static function init()
    {
        $fn = self::checkErrors(...);
        $fn();
    }
}

function main() {
    Worker::init();
}
?>
--EXPECT--
string(19) "Worker::checkErrors"