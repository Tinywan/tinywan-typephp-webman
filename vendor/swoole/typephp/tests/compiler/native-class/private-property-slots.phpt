--TEST--
Native class: inherited methods retain access to distinct private properties
--FILE--
<?php

#[Native]
class NativePrivateBase
{
    private int $baseValue = 10;

    public function getBaseValue(): int
    {
        return $this->baseValue;
    }

    public function setBaseValue(int $value): void
    {
        $this->baseValue = $value;
    }
}

#[Native]
class NativePrivateChild extends NativePrivateBase
{
    private int $childValue = 20;

    public function getChildValue(): int
    {
        return $this->childValue;
    }

    public function setChildValue(int $value): void
    {
        $this->childValue = $value;
    }
}

function main(): void
{
    $value = new NativePrivateChild();
    var_dump($value->getBaseValue(), $value->getChildValue());
    $value->setBaseValue(11);
    $value->setChildValue(22);
    var_dump($value->getBaseValue(), $value->getChildValue());
}
?>
--EXPECT--
int(10)
int(20)
int(11)
int(22)
