<?php

#[Native]
#[Printer]
class Point
{
    public int $x;
    public int $y;

    function foo()
    {
        var_dump($this->x, $this->y);
    }

    function toBool(): bool
    {
        return $this->x != 0 || $this->y != 0;
    }
}

function bar(Point $point)
{
    $point->x += 333;
    $point->y += 777;
}

function main()
{
    $p = new Point();
    $p->x = 100;
    $p->y = 900;
    echo $p, "\n";
    $p->foo();

    $array = std::array(Point::class, 10);
    $array[0] = $p;

    echo $array[0], "\n";
    $array[0]->foo();

//    bar($array[0]);
//    echo $array[0], "\n";

    bar($p);
    echo $p, "\n";

    $p2 = new Point();
    if ($p2->toBool()) {
        echo "p2 is not null\n";
    }
}