--TEST--
mixed: 1
--FILE--
<?php

class Solution
{
    protected $grid;
    public $result = 0;

    public function __construct(array $grid)
    {
        $this->grid = $grid;
        $this->islandPerimeter();
    }

    public function islandPerimeter()
    {
        if (empty($this->grid) || empty($this->grid[0])) return 0;

        foreach ($this->grid as $key => $val) {

            foreach ($this->grid[0] as $k => $v) {
                if ($this->grid[$key][$k] == 0) continue;
                $this->result += 4;

                if ($key > 0 && $this->grid[$key - 1][$k] == 1) {
                    $this->result -= 2;
                }
                if ($k > 0 && $this->grid[$key][$k - 1] == 1) {
                    $this->result -= 2;
                }
            }
        }
    }
}

function main()
{
    $arr = [
        [0, 1, 0, 0],
        [1, 1, 1, 0],
        [0, 1, 0, 0],
        [1, 1, 0, 0]
    ];

    $result = new Solution($arr);
    var_dump($result->result);
}
?>
--EXPECT--
int(16)
