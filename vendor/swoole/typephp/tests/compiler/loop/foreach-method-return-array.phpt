--TEST--
foreach supports dynamic method calls returning arrays
--FILE--
<?php

final class ArrayProvider
{
    public function values(): array
    {
        return ['a' => 1, 'b' => 2];
    }
}

function main(): void
{
    $provider = new ArrayProvider();

    foreach ($provider->values() as $key => $value) {
        var_dump($key.':'.$value);
    }
}
?>
--EXPECT--
string(3) "a:1"
string(3) "b:2"
