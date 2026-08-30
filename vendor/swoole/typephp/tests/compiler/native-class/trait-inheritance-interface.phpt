--TEST--
Native class: trait composition, inheritance and interface contracts
--FILE--
<?php

interface Named
{
    public function label(): string;
}

trait HasCounter
{
    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }
}

#[Native]
class NativeBase
{
    use HasCounter;

    public function __construct(int $initial)
    {
        $this->count = $initial;
    }

    public function label(): string
    {
        return 'base';
    }
}

#[Native]
class NativeChild extends NativeBase implements Named
{
    public function __construct()
    {
        parent::__construct(2);
    }

    public function label(): string
    {
        return 'child';
    }
}

function nativeLabel(NativeBase $value): string
{
    return $value->label();
}

function makeNativeChild(): NativeChild
{
    return new NativeChild();
}

function main(): void
{
    $value = makeNativeChild();
    $value->increment();
    var_dump($value->label(), nativeLabel($value), $value->count, $value instanceof Named);
}

?>
--EXPECT--
string(5) "child"
string(5) "child"
int(3)
bool(true)
