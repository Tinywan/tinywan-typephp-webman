--TEST--
class const 001
--FILE--
<?php
class WorkerA
{
    public function foo(): array {
    }

    public function sort(array &$list) {
        sort($list);
    }

    public function run() {
        return $this->foo();
    }
}

class WorkerB extends WorkerA
{
    public function foo(): array {
        $list = [377, 64, 688, 2];
        $ref = &$list;
        $this->sort($ref);
        return $list;
    }
}

function main()
{
    $o = new WorkerB;
    var_dump($o->run());
}
?>
--EXPECT--
array(4) {
  [0]=>
  int(2)
  [1]=>
  int(64)
  [2]=>
  int(377)
  [3]=>
  int(688)
}