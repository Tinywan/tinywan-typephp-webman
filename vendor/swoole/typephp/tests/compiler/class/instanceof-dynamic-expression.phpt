--TEST--
instanceof dynamic class expression side effects
--FILE--
<?php

interface InstanceofMarker {}
class InstanceofSubject implements InstanceofMarker {}

function make_instanceof_subject(): object
{
    echo "object\n";
    return new InstanceofSubject();
}

function make_instanceof_class(string $class): string
{
    echo "class:$class\n";
    return $class;
}

function main(): void
{
    var_dump(make_instanceof_subject() instanceof (make_instanceof_class(InstanceofMarker::class)));
    var_dump(make_instanceof_subject() instanceof (make_instanceof_class(stdClass::class)));
}
?>
--EXPECT--
object
class:InstanceofMarker
bool(true)
object
class:stdClass
bool(false)
