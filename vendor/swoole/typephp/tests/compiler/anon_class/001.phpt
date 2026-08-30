--TEST--
Anonymous Classes - Runtime class definition
--FILE--
<?php
// Test basic anonymous class
function test_basic_anonymous() {
    $basic = new class {
        public function greet(): string {
            return "Hello from anonymous class";
        }
    };
    var_dump($basic->greet());
}

// Test anonymous class with constructor
class Greeter {
    private string $greeting;
    
    public function __construct(string $greeting = "Hello") {
        $this->greeting = $greeting;
    }
    
    public function getGreeting(): string {
        return $this->greeting;
    }
}

function test_anonymous_with_constructor() {
    $withConstructor = new class("Hi") extends Greeter {
        public function getGreeting(): string {
            return parent::getGreeting() . " World!";
        }
    };
    var_dump($withConstructor->getGreeting());
}

// Test anonymous class implementing interface
interface LoggerInterface {
    public function log(string $message): void;
    public function getLogs(): array;
}

function test_anonymous_interface() {
    $logger = new class implements LoggerInterface {
        private array $logs = [];
        
        public function log(string $message): void {
            $this->logs[] = date('Y-m-d H:i:s') . " - " . $message;
        }
        
        public function getLogs(): array {
            return $this->logs;
        }
    };
    
    $logger->log("First message");
    $logger->log("Second message");
    var_dump(count($logger->getLogs()));
}

// Test anonymous class with properties
function test_anonymous_properties() {
    $config = new class {
        public string $name = "Test";
        private int $value = 42;
        
        public function getValue(): int {
            return $this->value;
        }
        
        public function setValue(int $value): void {
            $this->value = $value;
        }
    };
    
    var_dump($config->name);
    var_dump($config->getValue());
    $config->setValue(100);
    var_dump($config->getValue());
}

// Test nested anonymous classes
function test_nested_anonymous() {
    $outer = new class {
        private object $inner;
        
        public function __construct() {
            $this->inner = new class {
                public function getMessage(): string {
                    return "From inner class";
                }
            };
        }
        
        public function getInnerMessage(): string {
            return $this->inner->getMessage();
        }
    };
    
    var_dump($outer->getInnerMessage());
}

// Test anonymous class in array
function test_anonymous_array() {
    $classes = [
        new class { public function getType() { return "A"; } },
        new class { public function getType() { return "B"; } },
        new class { public function getType() { return "C"; } },
    ];
    
    foreach ($classes as $class) {
        echo $class->getType() . "\n";
    }
}

// Test static method in anonymous class
function test_anonymous_static() {
    $static = new class {
        private static int $counter = 0;
        
        public static function increment(): int {
            return ++self::$counter;
        }
        
        public static function getCounter(): int {
            return self::$counter;
        }
    };
    
    var_dump($static::increment());
    var_dump($static::increment());
    var_dump($static::getCounter());
}

function main() {
    // Test basic anonymous
    test_basic_anonymous();
    
    // Test anonymous with constructor
    test_anonymous_with_constructor();
    
    // Test anonymous interface
    test_anonymous_interface();
    
    // Test anonymous properties
    test_anonymous_properties();
    
    // Test nested anonymous
    test_nested_anonymous();
    
    // Test anonymous array
    test_anonymous_array();
    
    // Test anonymous static
    test_anonymous_static();
}
?>
--EXPECT--
string(26) "Hello from anonymous class"
string(9) "Hi World!"
int(2)
string(4) "Test"
int(42)
int(100)
string(16) "From inner class"
A
B
C
int(1)
int(2)
int(2)
