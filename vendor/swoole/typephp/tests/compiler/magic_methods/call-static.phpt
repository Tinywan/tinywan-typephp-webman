--TEST--
Magic Methods - __get, __set, __call, __invoke etc.
--FILE--
<?php
class MagicClass {
    public static function __CallStatic(string $name, array $arguments): mixed {
        return "Called static method: {$name} with args: " . implode(', ', $arguments);
    }
}

function main() {
    var_dump(MagicClass::staticMethod('static1', 'static2'));
}
?>
--EXPECT--
string(62) "Called static method: staticMethod with args: static1, static2"