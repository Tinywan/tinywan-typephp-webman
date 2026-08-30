--TEST--
dynamic class constant names preserve PHP lookup and evaluation semantics
--FILE--
<?php

class DynamicConstantName
{
    public const ANSWER = 42;
    private const SECRET = 'secret';

    public static function secret(string $name): string
    {
        return self::{$name};
    }
}

enum DynamicConstantCase
{
    case READY;
}

function dynamic_constant_target(): string
{
    echo "target\n";
    return DynamicConstantName::class;
}

function dynamic_constant_name(): string
{
    echo "name\n";
    return 'ANSWER';
}

function main(): void
{
    $name = 'ANSWER';
    var_dump(DynamicConstantName::{$name});

    var_dump(dynamic_constant_target()::{dynamic_constant_name()});

    $className = 'class';
    var_dump(DynamicConstantName::{$className});
    $className = 'CLASS';
    var_dump(DynamicConstantName::{$className});

    $case = 'READY';
    var_dump(DynamicConstantCase::{$case} === DynamicConstantCase::READY);

    $secret = 'SECRET';
    var_dump(DynamicConstantName::secret($secret));
    try {
        DynamicConstantName::{$secret};
    } catch (Error $error) {
        echo $error->getMessage(), "\n";
    }

    $invalid = 1;
    try {
        DynamicConstantName::{$invalid};
    } catch (TypeError $error) {
        echo $error->getMessage(), "\n";
    }
}
?>
--EXPECT--
int(42)
target
name
int(42)
string(19) "DynamicConstantName"
string(19) "DynamicConstantName"
bool(true)
string(6) "secret"
Cannot access private constant DynamicConstantName::SECRET
Cannot use value of type int as class constant name
