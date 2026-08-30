--TEST--
type hits
--FILE--
<?php
class Select {
    public $errorHandler = null;
}

class Worker
{
    public static ?stdClass $globalEvent = null;
    public static string $eventLoopClass = 'Select';

    public static function init() {
        self::$globalEvent = new stdClass();
        var_dump(get_class(self::$globalEvent));
        self::$globalEvent = null;
        var_dump(self::$globalEvent);
        try {
            self::$globalEvent = new static::$eventLoopClass();
        } catch (TypeError $e) {
            var_dump($e->getMessage());
        }
    }
}

function main() {
    Worker::init();
}
?>
--EXPECT--
string(8) "stdClass"
NULL
string(60) "Worker::$globalEvent must be of type ?stdClass, object given"
