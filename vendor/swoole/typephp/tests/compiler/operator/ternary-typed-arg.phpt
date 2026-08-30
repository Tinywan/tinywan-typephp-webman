--TEST--
Ternary with captured statements keeps its static type for typed arguments
--FILE--
<?php
declare(strict_types=1);

class TernaryBoolArg
{
    public function takeBool(bool $flag): bool
    {
        return $flag;
    }
}

function main(): void
{
    $range = [1, 2, 3];
    $obj = new TernaryBoolArg();

    // The condition contains a function call, which forces the ternary into a
    // captured-statement lambda. The lambda must still yield php::Bool so it
    // can feed the typed parameter.
    var_dump($obj->takeBool(count($range) > 1 ? true : false));
    var_dump($obj->takeBool(count($range) > 5 ? true : false));
}
?>
--EXPECT--
bool(true)
bool(false)
