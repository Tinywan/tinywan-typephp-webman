--TEST--
static method
--FILE--
<?php
class Http {
    public static function input($data) {
        var_dump($data);
    }
}

class Worker {
    protected string $protocol = 'Http';

    public function run() {
        Http::input('http1.0');
        $this->protocol::input('http1.1');
    }
}

function main() {
    $worker = new Worker();
    $worker->run();
}
?>
--EXPECT--
string(7) "http1.0"
string(7) "http1.1"
