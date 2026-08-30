--TEST--
class entry registration preserves reverse-declared class and interface dependencies
--FILE--
<?php

interface RegistrationMarker
{
}

class RegistrationLeaf extends RegistrationMiddle implements RegistrationMarker
{
}

class RegistrationMiddle extends RegistrationRoot
{
}

class RegistrationRoot
{
    public function name(): string
    {
        return 'root';
    }
}

function main(): void
{
    $value = new RegistrationLeaf();
    var_dump($value instanceof RegistrationMiddle);
    var_dump($value instanceof RegistrationRoot);
    var_dump($value instanceof RegistrationMarker);
    echo $value->name(), "\n";
}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
root
