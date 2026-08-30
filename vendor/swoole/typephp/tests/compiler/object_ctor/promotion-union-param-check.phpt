--TEST--
Constructor property promotion checks union parameter before assignment
--ENV--
USE_ZEND_ALLOC=0
--FILE--
<?php
class PromotedUnionHolder
{
    public function __construct(public int|string $value)
    {
    }
}

function main(): void
{
    try {
        new PromotedUnionHolder([]);
    } catch (TypeError $e) {
        var_dump($e->getMessage());
    }
}
?>
--EXPECT--
string(96) "PromotedUnionHolder::__construct(): Argument #1 ($value) must be of type int|string, array given"
