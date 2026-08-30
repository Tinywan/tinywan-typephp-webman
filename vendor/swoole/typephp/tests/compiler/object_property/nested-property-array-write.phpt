--TEST--
nested object property array writes use generic property path
--FILE--
<?php

class NestedPropertyArrayWriteBlock
{
    public array $predecessors = [];
    public array $phi = [];
}

class NestedPropertyArrayWriteGraph
{
    /** @var array<int, NestedPropertyArrayWriteBlock> */
    public array $blocks = [];

    public function run(): void
    {
        $this->blocks[1] = new NestedPropertyArrayWriteBlock();
        $this->blocks[1]->predecessors[] = 42;
        $this->blocks[1]->phi['x'] = 7;

        var_dump($this->blocks[1]->predecessors);
        var_dump($this->blocks[1]->phi);
    }
}

function main() {
    (new NestedPropertyArrayWriteGraph())->run();
}
?>
--EXPECT--
array(1) {
  [0]=>
  int(42)
}
array(1) {
  ["x"]=>
  int(7)
}
