--TEST--
empty()
--FILE--
<?php
class Foo
{
    protected $grid;
    public $result = 0;

    public function __construct(array $grid)
    {
        $this->grid = $grid;
    }

    public function bar()
    {
        var_dump(empty($this->grid));
        var_dump(empty($this->grid[0]));
        var_dump(empty($this->grid[1][2]));
        var_dump(empty($this->grid[1][5]));
    }
}

function main()
{
    $arr = [
        [0, 1, 0, 0],
        [1, 1, 1, 0],
    ];

    $o = new Foo($arr);
    $o->bar();
}
?>
--EXPECTF--
bool(false)
bool(false)
bool(false)
bool(true)
