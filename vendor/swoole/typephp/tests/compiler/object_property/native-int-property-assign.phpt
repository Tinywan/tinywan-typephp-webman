--TEST--
Native int property assignment follows strict property type rules
--FILE--
<?php
declare(strict_types=1);
class GridSize
{
    public int $size = 0;
}

class GridItem
{
    public int $colStart = 0;
    public int $colEnd = 0;
    public int $rowStart = 0;
    public int $rowEnd = 0;
    public int $w = 0;
    public int $h = 0;
    public ?object $style = null;
    public ?array $originalChildren = null;
}

class Cell
{
    public ?object $style = null;
    public ?array $children = null;
}

function main(): void
{
    $idx = 5;
    $numCols = 3;
    $cols = [new GridSize(), new GridSize(), new GridSize()];
    $rows = [new GridSize(), new GridSize()];
    $cols[2]->size = 120;
    $rows[1]->size = 30;

    $cr = new Cell();
    $cr->style = new stdClass();
    $cr->children = ['a', 'b'];

    $gi = new GridItem();
    $gi->colStart = $idx % $numCols;
    $gi->colEnd = $gi->colStart + 1;
    try {
        $gi->rowStart = $idx / $numCols;
    } catch (TypeError $e) {
        var_dump($e->getMessage());
    }
    $gi->rowStart = intdiv($idx, $numCols);
    $gi->rowEnd = $gi->rowStart + 1;
    $gi->w = $cols[$gi->colStart]->size;
    $gi->h = $gi->rowStart < count($rows) ? $rows[$gi->rowStart]->size : 50;
    $gi->style = $cr->style;
    $gi->originalChildren = $cr->children;

    var_dump($gi->colStart, $gi->colEnd, $gi->rowStart, $gi->rowEnd, $gi->w, $gi->h);
    var_dump($gi->style instanceof stdClass, $gi->originalChildren);
}
?>
--EXPECT--
string(63) "Cannot assign float to property GridItem::$rowStart of type int"
int(2)
int(3)
int(1)
int(2)
int(120)
int(30)
bool(true)
array(2) {
  [0]=>
  string(1) "a"
  [1]=>
  string(1) "b"
}
