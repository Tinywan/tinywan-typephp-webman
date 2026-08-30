--TEST--
object property array writes embedded in expressions should write once
--FILE--
<?php

class PropertyArrayWriteComposedNode
{
    public array $items = [];
}

class PropertyArrayWriteComposedBox
{
    public PropertyArrayWriteComposedNode $node;

    public function __construct()
    {
        $this->node = new PropertyArrayWriteComposedNode();
    }
}

function next_value(&$counter) {
    echo 'next:' . $counter . "\n";
    return ++$counter;
}

function main() {
    $box = new PropertyArrayWriteComposedBox();
    $counter = 0;

    $a = ($box->node->items[] = next_value($counter));
    $b = true ? ($box->node->items['k'] = next_value($counter)) : 99;
    $c = match ($counter) {
        2 => ($box->node->items[] = next_value($counter)),
        default => 0,
    };

    var_dump($a, $b, $c);
    var_dump($box->node->items);
    var_dump($counter);
}
?>
--EXPECT--
next:0
next:1
next:2
int(1)
int(2)
int(3)
array(3) {
  [0]=>
  int(1)
  ["k"]=>
  int(2)
  [1]=>
  int(3)
}
int(3)
