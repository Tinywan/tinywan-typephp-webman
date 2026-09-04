--TEST--
ArrayAccess ??= writes common nested and magic-property targets
--FILE--
<?php

final class IndirectBag implements ArrayAccess
{
    public array $calls = [];

    public function __construct(public array $data = [])
    {
    }

    public function offsetExists(mixed $offset): bool
    {
        $this->calls[] = "exists:$offset";
        return array_key_exists($offset, $this->data);
    }

    public function offsetGet(mixed $offset): mixed
    {
        $this->calls[] = "get:$offset";
        return $this->data[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->calls[] = "set:$offset";
        $this->data[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }
}

final class IndirectOuterBag implements ArrayAccess
{
    public function __construct(private IndirectBag $bag)
    {
    }

    public function offsetExists(mixed $offset): bool
    {
        return $offset === 'bag';
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->bag;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
    }

    public function offsetUnset(mixed $offset): void
    {
    }
}

final class IndirectMagicHolder
{
    public function __construct(private IndirectBag $bag)
    {
    }

    public function __get(string $name): mixed
    {
        return $this->bag;
    }
}

function showIndirectResult(string $label, mixed $result, IndirectBag $bag): void
{
    echo $label, ':', json_encode([$result, $bag->data, $bag->calls]), "\n";
}

function main(): void
{
    $nestedBag = new IndirectBag();
    $nested = ['bag' => $nestedBag];
    $result = ($nested['bag']['key'] ??= 51);
    showIndirectResult('array', $result, $nestedBag);

    $innerBag = new IndirectBag();
    $outerBag = new IndirectOuterBag($innerBag);
    $result = ($outerBag['bag']['key'] ??= 52);
    showIndirectResult('array-access', $result, $innerBag);

    $magicBag = new IndirectBag();
    $magic = new IndirectMagicHolder($magicBag);
    $result = ($magic->virtual['key'] ??= 53);
    showIndirectResult('magic-property', $result, $magicBag);
}
?>
--EXPECT--
array:[51,{"key":51},["exists:key","set:key"]]
array-access:[52,{"key":52},["exists:key","set:key"]]
magic-property:[53,{"key":53},["exists:key","set:key"]]
