--TEST--
Trait methods take precedence over __call magic fallback
--FILE--
<?php

trait AttributeLookup
{
    public function getAttribute(string $class): string
    {
        return 'attribute:' . $class;
    }
}

class TraitMethodConsumer
{
    use AttributeLookup;

    public function __call(string $name, array $arguments): mixed
    {
        return 'magic:' . $name;
    }
}

function main(): void
{
    $object = new TraitMethodConsumer();
    var_dump($object->getAttribute('SomeClass'));
    var_dump($object->missingMethod());
}
?>
--EXPECT--
string(19) "attribute:SomeClass"
string(19) "magic:missingMethod"
