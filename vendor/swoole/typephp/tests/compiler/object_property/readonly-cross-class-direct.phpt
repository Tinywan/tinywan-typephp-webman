--TEST--
Readonly native properties remain initialized when read across classes
--FILE--
<?php

use native_types;

class ReadonlyDimensions
{
    public readonly int $x;
    public readonly int $y;
    public readonly float $scale;

    public function __construct(int $x, int $y, float $scale)
    {
        $this->x = $x;
        $this->y = $y;
        $this->scale = $scale;
    }

    public function values(): array
    {
        return [$this->x, $this->y, $this->scale];
    }
}

class MutableDimensions
{
    public int $x;

    public function __construct(int $x)
    {
        $this->x = $x;
    }
}

readonly class ReadonlyClassDimensions
{
    public int $x;

    public function __construct(int $x)
    {
        $this->x = $x;
    }
}

class DimensionsReader
{
    public static function readonlyValues(ReadonlyDimensions $value): array
    {
        return [$value->x, $value->y, $value->scale];
    }

    public static function mutableValue(MutableDimensions $value): int
    {
        return $value->x;
    }

    public static function readonlyClassValue(ReadonlyClassDimensions $value): int
    {
        return $value->x;
    }
}

function main(): void
{
    $readonly = new ReadonlyDimensions(10, 20, 1.5);
    $mutable = new MutableDimensions(30);
    $readonlyClass = new ReadonlyClassDimensions(40);

    var_dump(DimensionsReader::readonlyValues($readonly));
    var_dump($readonly->values());
    var_dump(DimensionsReader::mutableValue($mutable));
    var_dump(DimensionsReader::readonlyClassValue($readonlyClass));
}
?>
--EXPECT--
array(3) {
  [0]=>
  int(10)
  [1]=>
  int(20)
  [2]=>
  float(1.5)
}
array(3) {
  [0]=>
  int(10)
  [1]=>
  int(20)
  [2]=>
  float(1.5)
}
int(30)
int(40)
