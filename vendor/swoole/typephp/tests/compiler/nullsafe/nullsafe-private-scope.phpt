--TEST--
Nullsafe method calls preserve private visibility when required
--FILE--
<?php

final class NullsafePrivateScope
{
    private function value(): string
    {
        return 'private-ok';
    }

    public function read(?self $object): ?string
    {
        return $object?->value();
    }
}

function main(): void
{
    $object = new NullsafePrivateScope();
    var_dump($object->read($object));
    var_dump($object->read(null));
}

?>
--EXPECT--
string(10) "private-ok"
NULL
