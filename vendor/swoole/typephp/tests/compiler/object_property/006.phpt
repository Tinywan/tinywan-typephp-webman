--TEST--
default array property
--FILE--
<?php
class Foo
{
    public array $board;

    public function run(): void
    {
        $this->board = [];
        $board = [];
        for ($i = 0; $i < 4; $i++) {
            $this->board[$i] = 999 + $i;
            $board[$i] = 112 * $i;
        }
        var_dump($board);
        var_dump($this->board);
    }
}

function main() {
    $o = new Foo;
    $o->run();
}
?>
--EXPECT--
array(4) {
  [0]=>
  int(0)
  [1]=>
  int(112)
  [2]=>
  int(224)
  [3]=>
  int(336)
}
array(4) {
  [0]=>
  int(999)
  [1]=>
  int(1000)
  [2]=>
  int(1001)
  [3]=>
  int(1002)
}