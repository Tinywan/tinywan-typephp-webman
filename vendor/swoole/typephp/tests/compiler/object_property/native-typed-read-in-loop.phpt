--TEST--
Native typed object property read inside loop falls back to typed zval value
--FILE--
<?php
use native_types;

class NativeTypedLoopData
{
    public int $val = 0;
    public int $result = 0;
}

function process(array $lookup): array
{
    $r1 = 0;
    $r2 = 0;

    for ($i = 0; $i < 1; $i++) {
        $gi = new NativeTypedLoopData();
        $gi->val = $i;
        $gi->result = $gi->val + 1;

        $r1 = $lookup[$gi->val];
        $r2 = $gi->val < 10 ? $lookup[$gi->val] : 99;
    }

    return [$gi->result, $r1, $r2];
}

function main(): void
{
    $r = process([10, 20, 30]);
    var_dump($r);
}
?>
--EXPECT--
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(10)
  [2]=>
  int(10)
}
