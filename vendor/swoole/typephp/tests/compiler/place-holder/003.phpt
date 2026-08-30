--TEST--
place-holder
--FILE--
<?php
interface EventInterface {}

class Select implements EventInterface {
    public function onReadable($stream, callable $func): void
    {
        $func($stream);
    }
}
class Worker {
    public static ?EventInterface $globalEvent = null;
    protected $mainSocket;

    function __construct() {
        $this->mainSocket = 'tcp-stream';
    }

    public function resumeAccept()
    {
        static::$globalEvent->onReadable($this->mainSocket, $this->acceptUdpConnection(...));
    }

    protected function acceptUdpConnection($stream)
    {
         var_dump(__METHOD__);
         var_dump($stream);
    }
}

function main()
{
    $obj = new Worker;
    Worker::$globalEvent = new Select;
    $obj->resumeAccept();
}
?>
--EXPECT--
string(27) "Worker::acceptUdpConnection"
string(10) "tcp-stream"
