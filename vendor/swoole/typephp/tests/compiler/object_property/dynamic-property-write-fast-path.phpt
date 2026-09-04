--TEST--
Dynamic property statement writes preserve scope, evaluation and reference value semantics
--FILE--
<?php
declare(strict_types=1);

final class DynamicWriter
{
    private int $hidden = 0;

    public function write(string $name, mixed $value): void
    {
        $this->$name = $value;
    }

    public function writeFromReference(string $name, mixed &$value): void
    {
        $this->$name = $value;
    }

    public function writeComputed(string $name, int &$calls): void
    {
        $this->$name = nextDynamicValue($calls);
    }

    public function value(): int
    {
        return $this->hidden;
    }
}

function nextDynamicValue(int &$calls): int
{
    $calls++;
    return 41;
}

function main(): void
{
    $writer = new DynamicWriter();
    $name = 'hidden';
    $writer->write($name, 17);
    var_dump($writer->value());

    $calls = 0;
    $writer->writeComputed($name, $calls);
    var_dump($writer->value(), $calls);

    $source = 42;
    $writer->writeFromReference($name, $source);
    $source = 43;
    var_dump($writer->value(), $source);
}
?>
--EXPECT--
int(17)
int(41)
int(1)
int(42)
int(43)
