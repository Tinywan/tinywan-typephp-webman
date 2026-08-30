--TEST--
is_callable with array callables and dynamic method names
--FILE--
<?php

class IsCallableArrayTarget
{
    public function run(): void
    {
    }

    public static function stat(): void
    {
    }
}

function main(): void
{
    $object = new IsCallableArrayTarget();
    $method = 'run';
    $static = 'stat';

    var_dump(is_callable([$object, $method]));
    var_dump(is_callable([IsCallableArrayTarget::class, $static]));
    var_dump(is_callable([$object, 'missing']));
}
?>
--EXPECT--
bool(true)
bool(true)
bool(false)
