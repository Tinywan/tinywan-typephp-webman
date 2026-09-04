--TEST--
Dynamic call caches preserve polymorphic, object, and magic dispatch
--FILE--
<?php

function cached_first(int $value): string
{
    return 'first:' . $value;
}

function cached_second(int $value): string
{
    return 'second:' . $value;
}

class CachedMethodFirst
{
    public function run(int $value): string
    {
        return 'method-first:' . $value;
    }
}

class CachedMethodSecond
{
    public function run(int $value): string
    {
        return 'method-second:' . $value;
    }
}

class CachedMagicMethod
{
    public function __call(string $name, array $arguments): string
    {
        return 'magic-' . $name . ':' . $arguments[0];
    }
}

class CachedStaticMethod
{
    public static function run(int $value): string
    {
        return 'static:' . $value;
    }
}

function invoke_function(mixed $callback, int $value): string
{
    return $callback($value);
}

function invoke_method(object $object, mixed $method, int $value): string
{
    return $object->$method($value);
}

function main(): void
{
    $callbacks = ['cached_first', 'cached_second', 'cached_first'];
    foreach ($callbacks as $index => $callback) {
        var_dump(invoke_function($callback, $index));
    }

    $static = 'CachedStaticMethod::run';
    var_dump(invoke_function($static, 3));

    $closure = static fn (int $value): string => 'closure:' . $value;
    var_dump(invoke_function($closure, 4));

    $objects = [new CachedMethodFirst(), new CachedMethodSecond(), new CachedMagicMethod()];
    foreach ($objects as $index => $object) {
        var_dump(invoke_method($object, $index === 2 ? 'missing' : 'run', $index + 5));
    }
}
?>
--EXPECT--
string(7) "first:0"
string(8) "second:1"
string(7) "first:2"
string(8) "static:3"
string(9) "closure:4"
string(14) "method-first:5"
string(15) "method-second:6"
string(15) "magic-missing:7"
