--TEST--
Typed-reference ABI supports functions, methods, constructors, interfaces, and named arguments
--FILE--
<?php

interface TypedReferenceMutator
{
    public function mutate(string &$value, int $amount): void;
}

final class TypedReferenceTarget implements TypedReferenceMutator
{
    public function __construct(int &$value)
    {
        $value += 10;
    }

    public function mutate(string &$value, int $amount): void
    {
        $value .= ':' . $amount;
    }

    public static function toggle(bool &$value): void
    {
        $value = !$value;
    }
}

function widenFloat(float &$value): void
{
    // Assigning an integer to a float reference writes a float value.
    $value = 7;
}

function mutateThroughInterface(TypedReferenceMutator $target, string &$value): void
{
    $target->mutate($value, 4);
}

function main(): void
{
    $number = 1;
    new TypedReferenceTarget(value: $number);

    $text = 'value';
    $target = new TypedReferenceTarget($number);
    $target->mutate(amount: 3, value: $text);
    mutateThroughInterface($target, $text);

    $enabled = false;
    TypedReferenceTarget::toggle(value: $enabled);

    $float = 1.5;
    widenFloat(value: $float);

    var_dump($number, $text, $enabled, $float);
}

?>
--EXPECT--
int(21)
string(9) "value:3:4"
bool(true)
float(7)
