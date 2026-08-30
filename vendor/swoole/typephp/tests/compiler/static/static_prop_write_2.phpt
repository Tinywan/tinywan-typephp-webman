--TEST--
Static Class Property Read/Write Test
--FILE--
<?php
class Worker
{
    public static ?stdClass $event = null;
    public static string $eventLoopClass = 'Select';

    public static function init() {
        static::$eventLoopClass = static::$event ?: static::$eventLoopClass;
        var_dump(static::$eventLoopClass);
    }
}

function main() {
    Worker::init();
}
?>
--EXPECT--
string(6) "Select"