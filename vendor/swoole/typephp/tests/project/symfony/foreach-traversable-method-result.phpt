--TEST--
Symfony pattern: foreach over Traversable returned from method
--FILE--
<?php

final class TraversableProvider
{
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator(['first' => 1, 'second' => 2]);
    }
}

function main(): void
{
    $provider = new TraversableProvider();

    foreach ($provider->getIterator() as $key => $value) {
        var_dump($key.':'.$value);
    }
}
?>
--EXPECT--
string(7) "first:1"
string(8) "second:2"
