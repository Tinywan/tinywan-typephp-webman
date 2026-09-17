--TEST--
Native class: non-escaping local allocation uses stack storage
--FILE--
<?php

#[Native]
class StackPoint
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

function stackValue(): int
{
    $point = new StackPoint(20, 22);
    $point->x++;
    return $point->sum() - 1;
}

function main(): void
{
    var_dump(stackValue());
}

?>
--EXPECT--
int(42)
