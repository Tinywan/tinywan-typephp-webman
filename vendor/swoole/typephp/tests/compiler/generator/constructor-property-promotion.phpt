--TEST--
constructor property promotion runs even when the constructor contains yield
--FILE--
<?php
class PromotedGeneratorConstructor
{
    public function __construct(public int $value)
    {
        if (false) {
            yield 1;
        }
    }
}

function main(): void
{
    $object = new PromotedGeneratorConstructor(42);
    var_dump($object->value);
}
?>
--EXPECT--
int(42)
