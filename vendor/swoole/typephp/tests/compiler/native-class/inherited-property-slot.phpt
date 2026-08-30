--TEST--
Native class: compatible inherited property declarations reuse one C++ field
--FILE--
<?php

#[Native]
class NativePropertyBase
{
    public int $value = 1;

    public function writeFromBase(int $value): void
    {
        $this->value = $value;
    }
}

#[Native]
class NativePropertyChild extends NativePropertyBase
{
    public int $value = 2;

    public function readFromChild(): int
    {
        return $this->value;
    }
}

function main(): void
{
    $value = new NativePropertyChild();
    var_dump($value->readFromChild());
    $value->writeFromBase(42);
    var_dump($value->readFromChild());
}
?>
--EXPECT--
int(2)
int(42)
