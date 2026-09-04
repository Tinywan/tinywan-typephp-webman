--TEST--
By-reference variadic signatures remain compatible across interfaces and inheritance
--FILE--
<?php
declare(strict_types=1);

interface IncrementContract
{
    public function increment(int &...$values): void;
}

abstract class IncrementBase implements IncrementContract
{
    abstract public function increment(int &...$values): void;
}

final class Incrementer extends IncrementBase
{
    public function increment(int &...$values): void
    {
        foreach ($values as &$value) {
            $value++;
        }
        unset($value);
    }
}

function main(): void
{
    $incrementer = new Incrementer();
    $first = 10;
    $second = 20;
    $incrementer->increment($first, $second);
    var_dump($first, $second);
}
?>
--EXPECT--
int(11)
int(21)
