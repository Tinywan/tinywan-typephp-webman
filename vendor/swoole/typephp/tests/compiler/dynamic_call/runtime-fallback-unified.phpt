--TEST--
Dynamic class, function, callback and property chain use runtime fallback
--FILE--
<?php

class RuntimeFallbackLeaf
{
    public string $value = 'leaf';
}

class RuntimeFallbackBox
{
    public RuntimeFallbackLeaf $child;

    public function __construct()
    {
        $this->child = new RuntimeFallbackLeaf();
    }

    public function format(string $value): string
    {
        return 'method:' . $value;
    }
}

class RuntimeFallbackStatic
{
    public static string $value = 'before';

    public static function format(string $value): string
    {
        return 'static:' . $value;
    }
}

function runtime_fallback_function(string $value): string
{
    return 'function:' . $value;
}

function class_name(): string
{
    return RuntimeFallbackBox::class;
}

function function_name(): string
{
    return 'runtime_fallback_function';
}

function main(): void
{
    $class = class_name();
    $box = new $class();
    $property = 'child';
    $nested = 'value';
    var_dump($box->$property->$nested);
    $box->$property->$nested = 'changed';
    var_dump($box->$property->$nested);

    $function = function_name();
    var_dump($function('ok'));

    $method = 'format';
    $callback = [$box, $method];
    var_dump($callback('ok'));

    $staticClass = any(RuntimeFallbackStatic::class);
    $staticProperty = any('value');
    var_dump($staticClass::$$staticProperty);
    $staticClass::$$staticProperty = 'after';
    var_dump($staticClass::$$staticProperty);

    $staticMethod = any('format');
    var_dump($staticClass::$staticMethod('ok'));
}
?>
--EXPECT--
string(4) "leaf"
string(7) "changed"
string(11) "function:ok"
string(9) "method:ok"
string(6) "before"
string(5) "after"
string(9) "static:ok"
