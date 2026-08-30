--TEST--
Magic Methods - __get, __set, __call, __invoke etc.
--FILE--
<?php
class MagicClass {
    // __call for instance methods
    public function __call(string $name, array $arguments): mixed {
        return "Called method: {$name} with args: " . implode(', ', $arguments);
    }

    // __invoke
    public function __invoke(mixed $value): mixed {
        return "Invoked with: {$value}";
    }
    
    // __toString
    public function __toString(): string {
        return "MagicClass instance";
    }
    
    // __debugInfo
    public function __debugInfo(): array {
        return ['custom' => 'debug info', 'data' => 'hello world'];
    }
}

function main() {
    $magic = new MagicClass();

    // Test __call
    var_dump($magic->someMethod('arg1', 'arg2'));
    
    // Test __invoke
    var_dump($magic('hello'));
    
    // Test __toString
    echo $magic . "\n";
    
    // Test __debugInfo
    var_dump($magic);
}
?>
--EXPECT--
string(47) "Called method: someMethod with args: arg1, arg2"
string(19) "Invoked with: hello"
MagicClass instance
object(MagicClass)#1 (2) {
  ["custom"]=>
  string(10) "debug info"
  ["data"]=>
  string(11) "hello world"
}