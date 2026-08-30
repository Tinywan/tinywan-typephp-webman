--TEST--
Zend wrappers validate required, optional, variadic and excessive arguments
--FILE--
<?php
declare(strict_types=1);
function noArgs() {
    echo "noArgs\n";
}

function hello(string $value) {
    echo "hello:$value\n";
}

class Greeter {
    public function hello(string $value, string $suffix = '!'): void {
        echo "method:$value$suffix\n";
    }

    public static function staticHello(string $value): void {}
}

function world(callable $callback) {
    $callback('a');
}

function variadic(string $value, ...$results) {
    var_dump($value, $results);
}

function main()
{
    try {
        $noArgs = 'noArgs';
        $noArgs('extra');
    } catch (ArgumentCountError $e) {
        var_dump($e->getMessage());
    }

    try {
        $hello = 'hello';
        $hello('value', 'extra');
    } catch (ArgumentCountError $e) {
        var_dump($e->getMessage());
    }

    $noArgsClosure = static function (): void {
        echo "closure\n";
    };
    try {
        $noArgsClosure('closure-extra');
    } catch (ArgumentCountError $e) {
        var_dump($e->getMessage());
    }

    try {
        $callback = 'hello';
        $callback();
    } catch (ArgumentCountError $e) {
        var_dump($e->getMessage());
    }

    try {
        $method = [new Greeter(), 'hello'];
        $method();
    } catch (ArgumentCountError $e) {
        var_dump($e->getMessage());
    }

    try {
        $method('value', '!', 'extra');
    } catch (ArgumentCountError $e) {
        var_dump($e->getMessage());
    }

    try {
        $staticMethod = [Greeter::class, 'staticHello'];
        $staticMethod();
    } catch (ArgumentCountError $e) {
        var_dump($e->getMessage());
    }

    try {
        world(function(string $value, string $value1) {

        });
    } catch (ArgumentCountError $e) {
        var_dump($e->getMessage());
    }

    try {
        $callable = "variadic";
        $callable();
    } catch (ArgumentCountError $e) {
        var_dump($e->getMessage());
    }

    $callable('1', 1, 2, 3, 4, 5);
}
?>
--EXPECT--
string(45) "noArgs() expects exactly 0 arguments, 1 given"
string(43) "hello() expects exactly 1 argument, 2 given"
string(58) "stdClass::{closure}() expects exactly 0 arguments, 1 given"
string(43) "hello() expects exactly 1 argument, 0 given"
string(53) "Greeter::hello() expects at least 1 argument, 0 given"
string(53) "Greeter::hello() expects at most 2 arguments, 3 given"
string(58) "Greeter::staticHello() expects exactly 1 argument, 0 given"
string(58) "stdClass::{closure}() expects exactly 2 arguments, 1 given"
string(47) "variadic() expects at least 1 argument, 0 given"
string(1) "1"
array(5) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
  [3]=>
  int(4)
  [4]=>
  int(5)
}
