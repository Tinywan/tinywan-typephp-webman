--TEST--
Dynamic instantiation with named arguments and dynamic method call
--FILE--
<?php
declare(strict_types=1);

class HelloController {
    public string $prefix;

    public function __construct(string $prefix = 'Hello') {
        $this->prefix = $prefix;
    }

    public function greet(string $name, int $times): string {
        $result = '';
        for ($i = 0; $i < $times; $i++) {
            $result .= "{$this->prefix} {$name}!\n";
        }
        return $result;
    }

    public function farewell(string $name): string {
        return "{$this->prefix} Goodbye {$name}!";
    }
}

function main(): void {
    $handler = [HelloController::class, 'greet'];
    $params = ['World', 2];

    [$class, $method] = $handler;
    // Dynamic instantiation with named args + dynamic method call with unpacking
    $result = (new $class(prefix: 'Hi'))->$method(name: $params[0], times: $params[1]);
    echo $result;

    // Another dynamic method call: farewell
    $handler[1] = 'farewell';
    [$class, $method] = $handler;
    echo (new $class(prefix: 'Ohayo'))->$method(name: 'AOT');
}
?>
--EXPECT--
Hi World!
Hi World!
Ohayo Goodbye AOT!
