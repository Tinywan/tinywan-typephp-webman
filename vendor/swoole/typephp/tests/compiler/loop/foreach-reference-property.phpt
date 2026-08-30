--TEST--
foreach by reference supports live mutation of object array properties
--FILE--
<?php

class ForeachReferenceNode
{
    public array $children = [];
}

function main(): void
{
    $node = new ForeachReferenceNode();
    $node->children = ['a', 'b', 'c'];
    $copy = $node->children;

    foreach ($node->children as &$child) {
        $child = 'X' . $child;
    }
    unset($child);

    var_dump($node->children, $copy);

    $node->children = [1, 2];
    $copy = $node->children;
    $seen = [];
    foreach ($node->children as $key => &$child) {
        $seen[] = [$key, $child];
        if ($key === 0) {
            $node->children[] = 3;
        }
        $child *= 10;
    }
    unset($child);

    var_dump($seen, $node->children, $copy);

    $node->children = [1, 2, 3, 4];
    $seen = [];
    foreach ($node->children as $key => &$child) {
        $seen[] = $key;
        if ($key === 0) {
            unset($node->children[1]);
        }
        $child *= 10;
    }
    unset($child);

    var_dump($seen, $node->children);

    $root = new ForeachReferenceNode();
    $original = new ForeachReferenceNode();
    $root->children = [$original];

    $replaceChildren = static function (ForeachReferenceNode $current): void {
        foreach ($current->children as &$child) {
            $child = new ForeachReferenceNode();
        }
        unset($child);
    };
    $replaceChildren($root);

    var_dump($root->children[0] !== $original);
}
?>
--EXPECT--
array(3) {
  [0]=>
  string(2) "Xa"
  [1]=>
  string(2) "Xb"
  [2]=>
  string(2) "Xc"
}
array(3) {
  [0]=>
  string(1) "a"
  [1]=>
  string(1) "b"
  [2]=>
  string(1) "c"
}
array(3) {
  [0]=>
  array(2) {
    [0]=>
    int(0)
    [1]=>
    int(1)
  }
  [1]=>
  array(2) {
    [0]=>
    int(1)
    [1]=>
    int(2)
  }
  [2]=>
  array(2) {
    [0]=>
    int(2)
    [1]=>
    int(3)
  }
}
array(3) {
  [0]=>
  int(10)
  [1]=>
  int(20)
  [2]=>
  int(30)
}
array(2) {
  [0]=>
  int(1)
  [1]=>
  int(2)
}
array(3) {
  [0]=>
  int(0)
  [1]=>
  int(2)
  [2]=>
  int(3)
}
array(3) {
  [0]=>
  int(10)
  [2]=>
  int(30)
  [3]=>
  int(40)
}
bool(true)
