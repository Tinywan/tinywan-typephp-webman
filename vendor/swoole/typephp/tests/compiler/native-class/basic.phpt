--TEST--
Native class: construction, typed properties and direct method calls
--FILE--
<?php

#[Native]
class Point
{
    public int $x = 0;
    public int $y = 0;

    public function __construct(int $x, int $y)
    {
        $this->x = $x;
        $this->y = $y;
    }

    public function sum(): int
    {
        return $this->x + $this->y;
    }
}

function main(): void
{
    $point = new Point(20, 22);
    var_dump($point->x, $point->y, $point->sum());
    unset($point);
    try {
        $point->sum();
    } catch (Error $error) {
        echo "null guarded\n";
    }
}

?>
--EXPECT--
int(20)
int(22)
int(42)
null guarded
