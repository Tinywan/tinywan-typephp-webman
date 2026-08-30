--TEST--
Native class: __toString and __invoke lower to direct native calls
--FILE--
<?php

#[Native]
class NativeCallableLabel
{
    public string $label = 'native';

    public function __toString(): string
    {
        return $this->label;
    }

    public function __invoke(int $number): string
    {
        return $this->label . ':' . $number;
    }
}

function main(): void
{
    $value = new NativeCallableLabel();
    echo $value, "\n";
    var_dump((string) $value);
    var_dump('value=' . $value);
    var_dump($value(42));
    var_dump($value instanceof Stringable);
}
?>
--EXPECT--
native
string(6) "native"
string(12) "value=native"
string(9) "native:42"
bool(true)
