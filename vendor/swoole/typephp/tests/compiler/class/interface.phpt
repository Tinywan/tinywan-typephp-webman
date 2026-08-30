--TEST--
interface implementation
--FILE--
<?php

interface Logger {
    public function log(string $message): void;
}

interface Formatter {
    public function format(string $message): string;
}

class ConsoleLogger implements Logger {
    public function log(string $message): void {
        echo "LOG: " . $message . "\n";
    }
}

class JsonLogger implements Logger, Formatter {
    private array $entries = [];

    public function log(string $message): void {
        $this->entries[] = $message;
    }

    public function format(string $message): string {
        return '{"msg":"' . $message . '"}';
    }

    public function getEntries(): array {
        return $this->entries;
    }
}

function main() {
    $console = new ConsoleLogger();
    $console->log("test message");

    $json = new JsonLogger();
    $json->log("hello");
    $json->log("world");
    var_dump($json->getEntries());
    var_dump($json->format("hi"));

    $logger = $json;
    var_dump($logger instanceof Logger);
    var_dump($logger instanceof Formatter);

    echo "done\n";
}

?>
--EXPECT--
LOG: test message
array(2) {
  [0]=>
  string(5) "hello"
  [1]=>
  string(5) "world"
}
string(12) "{"msg":"hi"}"
bool(true)
bool(true)
done
