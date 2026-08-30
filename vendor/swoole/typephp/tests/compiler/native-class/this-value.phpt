--TEST--
Native class: $this is a native object pointer in value contexts
--FILE--
<?php

#[Native]
class NativeThisValue
{
    public int $value;

    public function __construct(int $value)
    {
        $this->value = $value;
    }

    public function identity(): self
    {
        return $this;
    }

    public function sameAs(NativeThisValue $other): bool
    {
        $alias = $this;
        return $alias === $other;
    }

    public function passToFunction(): int
    {
        return readNativeThisValue($this);
    }
}

function readNativeThisValue(NativeThisValue $value): int
{
    return $value->value;
}

function main(): void
{
    $value = new NativeThisValue(42);
    $other = new NativeThisValue(42);

    var_dump($value->identity() === $value);
    var_dump($value->sameAs($value));
    var_dump($value->sameAs($other));
    var_dump($value->passToFunction());
}

?>
--EXPECT--
bool(true)
bool(true)
bool(false)
int(42)
