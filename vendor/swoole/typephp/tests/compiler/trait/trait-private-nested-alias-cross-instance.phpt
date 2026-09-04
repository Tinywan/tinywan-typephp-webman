--TEST--
Private, nested and aliased trait methods retain class scope across instances
--FILE--
<?php

trait NestedPrivateMethod
{
    private function nestedSecret(): string
    {
        return 'nested';
    }
}

trait NestedPrivateComposition
{
    use NestedPrivateMethod;
}

trait AliasMethodSource
{
    protected function sourceSecret(): string
    {
        return 'alias';
    }
}

class PrivateTraitConsumer
{
    use NestedPrivateComposition;
    use AliasMethodSource {
        sourceSecret as private aliasSecret;
    }

    public function readOther(): string
    {
        $other = new static();
        return $other->nestedSecret() . ':' . $other->aliasSecret();
    }
}

class PrivateTraitChild extends PrivateTraitConsumer
{
}

function main(): void
{
    echo (new PrivateTraitConsumer())->readOther(), "\n";
    echo (new PrivateTraitChild())->readOther(), "\n";
}
?>
--EXPECT--
nested:alias
nested:alias
