--TEST--
Native class: abstract methods dispatch through the native virtual thunk
--FILE--
<?php

#[Native]
abstract class NativeAbstractValue
{
    abstract public function value(): int;

    abstract public function label(string $prefix = 'value'): string;
}

#[Native]
class NativeConcreteValue extends NativeAbstractValue
{
    public function value(): int
    {
        return 42;
    }

    public function label(string $prefix = 'concrete'): string
    {
        return $prefix . '=' . $this->value();
    }
}

function readAbstractValue(NativeAbstractValue $value): int
{
    return $value->value();
}

function readAbstractLabel(NativeAbstractValue $value): string
{
    return $value->label();
}

function main(): void
{
    $value = new NativeConcreteValue();
    var_dump(readAbstractValue($value));
    var_dump(readAbstractLabel($value));
}
?>
--EXPECT--
int(42)
string(11) "concrete=42"
