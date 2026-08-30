--TEST--
Nullsafe chain does not suppress normal method call on null return value
--FILE--
<?php

class NullsafeNormalChainRoot
{
    public function next(): ?NullsafeNormalChainLeaf
    {
        return null;
    }
}

class NullsafeNormalChainLeaf
{
    public function value(): string
    {
        return 'value';
    }
}

function main(): void
{
    $root = new NullsafeNormalChainRoot();

    try {
        var_dump($root?->next()->value());
        echo "not caught\n";
    } catch (Error $e) {
        echo "caught\n";
    }
}
?>
--EXPECT--
caught
