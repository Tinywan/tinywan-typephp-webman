--TEST--
Abstract and interface implementations may add a by-reference return
--FILE--
<?php

abstract class AbstractValueSource
{
    abstract public function abstractValue(): int;
}

interface ValueSourceContract
{
    public function interfaceValue(): int;
}

final class ReferenceValueSource extends AbstractValueSource implements ValueSourceContract
{
    private int $abstractStored = 5;
    private int $interfaceStored = 7;

    public function &abstractValue(): int
    {
        return $this->abstractStored;
    }

    public function &interfaceValue(): int
    {
        return $this->interfaceStored;
    }
}

function main(): void
{
    $source = new ReferenceValueSource();

    $abstractAlias =& $source->abstractValue();
    $abstractAlias = 50;
    var_dump($source->abstractValue());

    $interfaceAlias =& $source->interfaceValue();
    $interfaceAlias = 70;
    var_dump($source->interfaceValue());
}
?>
--EXPECT--
int(50)
int(70)
