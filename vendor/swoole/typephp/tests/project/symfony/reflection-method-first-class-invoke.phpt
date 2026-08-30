--TEST--
Symfony pattern: cache ReflectionMethod::invoke first-class callable
--FILE--
<?php

function main(): void
{
    $invoke = null;
    $invoke ??= (new ReflectionMethod(ReflectionMethod::class, 'getName'))->invoke(...);

    $method = new ReflectionMethod(DateTimeZone::class, 'getName');
    var_dump($invoke($method));
}
?>
--EXPECT--
string(7) "getName"
