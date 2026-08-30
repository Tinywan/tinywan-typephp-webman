--TEST--
Native class: ArrayAccess syntax lowers to direct native method calls
--FILE--
<?php

#[Native]
class NativeArrayBag implements ArrayAccess
{
    public array $values = [];

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->values[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->values[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->values[] = $value;
        } else {
            $this->values[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->values[$offset]);
    }
}

function receiver(NativeArrayBag $bag): NativeArrayBag
{
    echo 'R';
    return $bag;
}

function keyValue(): string
{
    echo 'K';
    return 'ordered';
}

function assignedValue(): int
{
    echo 'V';
    return 42;
}

function countedKey(string $key): string
{
    echo 'Q';
    return $key;
}

function main(): void
{
    $bag = new NativeArrayBag();

    $bag['first'] = 1;
    $assigned = ($bag['second'] = 2);
    $bag[] = 3;
    $bag['nested'] = ['child' => 7];

    var_dump($bag['first']);
    var_dump($assigned);
    var_dump($bag[0]);
    var_dump($bag['nested']['child']);
    var_dump(isset($bag['nested']['child']));
    var_dump($bag['nested']['child'] ?? 'fallback');
    var_dump(isset($bag['second']));
    var_dump(empty($bag['missing']));
    var_dump($bag['missing'] ?? 'fallback');
    var_dump(isset($bag[countedKey('second')]));
    var_dump(empty($bag[countedKey('missing')]));
    var_dump($bag[countedKey('missing')] ?? 'fallback');

    unset($bag['second']);
    var_dump(isset($bag['second']));

    receiver($bag)[keyValue()] = assignedValue();
    echo PHP_EOL;
    $ordered = ($bag['result'] = 42);
    var_dump($ordered, $bag['ordered']);
}
?>
--EXPECT--
int(1)
int(2)
int(3)
int(7)
bool(true)
int(7)
bool(true)
bool(true)
string(8) "fallback"
Qbool(true)
Qbool(true)
Qstring(8) "fallback"
bool(false)
RKV
int(42)
int(42)
