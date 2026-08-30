--TEST--
Static function, method, and constructor calls pass by-reference args automatically
--FILE--
<?php

function mutate_arg(&$value): void
{
    $value .= ':function';
}

function mutate_named_arg(string $prefix, &$value): void
{
    $value .= ':' . $prefix;
}

class RefTarget
{
    public function mutate(&$value): void
    {
        $value .= ':method';
    }

    public function mutateNamed(string $prefix, &$value): void
    {
        $value .= ':' . $prefix;
    }

    public function __construct(&$value)
    {
        $value .= ':ctor';
    }
}

class RefChild extends RefTarget
{
}

function main(): void
{
    $value = 'start';
    mutate_arg($value);
    $target = new RefTarget($value);
    $target->mutate($value);
    new RefChild($value);
    mutate_named_arg(...['named-function'], value: $value);
    $target->mutateNamed(...['named-method'], value: $value);
    $fn = 'mutate_named_arg';
    $fn(...['dynamic-refval'], value: refval($value));
    echo $value, PHP_EOL;
}

?>
--EXPECT--
start:function:ctor:method:ctor:named-function:named-method:dynamic-refval
