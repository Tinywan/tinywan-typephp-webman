--TEST--
Dynamic calls preserve cached names, dynamic names, polymorphism, and magic dispatch
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

class CachedStaticMethodSecond
{
    public static function run(int $value): string
    {
        return 'static-second:' . $value;
    }
}

class CachedStaticMagic
{
    public static function __callStatic(string $name, array $arguments): string
    {
        return 'static-magic-' . $name . ':' . $arguments[0];
    }
}

class CachedScopedMethod
{
    private function hidden(int $value): string
    {
        return 'scoped:' . $value;
    }

    public function invoke(mixed $method, int $value): string
    {
        return $this->$method($value);
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

function invoke_named_method(object $object, int $value): string
{
    return $object->run($value);
}

function invoke_named_magic(object $object, int $value): string
{
    return $object->missing($value);
}

function invoke_nullsafe_named_method(?object $object, int $value): ?string
{
    return $object?->run($value);
}

function invoke_nullsafe_dynamic_method(?object $object, mixed $method, int $value): ?string
{
    return $object?->$method($value);
}

function invoke_static_method(mixed $class, mixed $method, int $value): string
{
    return $class::$method($value);
}

function invoke_static_named_method(mixed $class, int $value): string
{
    return $class::run($value);
}

function invoke_named_class_dynamic_method(mixed $method, int $value): string
{
    return CachedStaticMethod::$method($value);
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

    var_dump(invoke_named_method($objects[0], 8));
    var_dump(invoke_named_method($objects[1], 9));
    var_dump(invoke_named_magic($objects[2], 10));

    var_dump(invoke_nullsafe_named_method($objects[0], 11));
    var_dump(invoke_nullsafe_named_method(null, 12));
    var_dump(invoke_nullsafe_named_method($objects[1], 13));
    var_dump(invoke_nullsafe_dynamic_method($objects[0], 'run', 14));
    var_dump(invoke_nullsafe_dynamic_method(null, 'run', 15));

    $scoped = new CachedScopedMethod();
    var_dump($scoped->invoke('hidden', 16));
    var_dump($scoped->invoke('hidden', 17));

    var_dump(invoke_static_method('CachedStaticMethod', 'run', 18));
    var_dump(invoke_static_method('CachedStaticMethodSecond', 'run', 19));
    var_dump(invoke_static_method('CachedStaticMagic', 'missing', 20));
    var_dump(invoke_static_named_method('CachedStaticMethod', 21));
    var_dump(invoke_named_class_dynamic_method('run', 22));
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
string(14) "method-first:8"
string(15) "method-second:9"
string(16) "magic-missing:10"
string(15) "method-first:11"
NULL
string(16) "method-second:13"
string(15) "method-first:14"
NULL
string(9) "scoped:16"
string(9) "scoped:17"
string(9) "static:18"
string(16) "static-second:19"
string(23) "static-magic-missing:20"
string(9) "static:21"
string(9) "static:22"
