--TEST--
integer, boolean and double remain class names in PHP type declarations
--FILE--
<?php
declare(strict_types=1);

class integer {}
class boolean {}
class double {}

function acceptInteger(integer $value): string
{
    return get_class($value);
}

function acceptBoolean(boolean $value): string
{
    return get_class($value);
}

function acceptDouble(double $value): string
{
    return get_class($value);
}

function main(): void
{
    echo acceptInteger(new integer()), "\n";
    echo acceptBoolean(new boolean()), "\n";
    echo acceptDouble(new double()), "\n";
}
?>
--EXPECT--
integer
boolean
double
