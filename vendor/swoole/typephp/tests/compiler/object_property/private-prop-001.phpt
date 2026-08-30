--TEST--
Static Class Property Read/Write Test
--FILE--
<?php
class Select {
    private $errorHandler = null;
    public function setErrorHandler($errorHandler): void
    {
        $this->errorHandler = $errorHandler;
        var_dump($this->errorHandler);
    }
}

class Worker
{
    public static ?Select $globalEvent = null;

    public static function init() {
        self::$globalEvent = new Select;
        self::$globalEvent->setErrorHandler('test');
    }
}

function main() {
    Worker::init();
}
?>
--EXPECT--
string(4) "test"
