--TEST--
dynamic static method name with side effects
--FILE--
<?php

class DynamicStaticMethodTarget
{
    public static function render(string $value): string
    {
        return 'render:' . $value;
    }
}

function choose_static_method(): string
{
    echo "method\n";
    return 'render';
}

function make_static_arg(): string
{
    echo "arg\n";
    return 'value';
}

function main(): void
{
    $method = choose_static_method();
    var_dump(DynamicStaticMethodTarget::$method(make_static_arg()));
}
?>
--EXPECT--
method
arg
string(12) "render:value"
