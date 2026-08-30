--TEST--
static closure use by reference through internal callback
--FILE--
<?php
function main(): void
{
    $seen = [];
    $values = [1, 2, 3];

    $result = array_map(
        static function (int $value) use (&$seen): int {
            $seen[] = $value;
            return $value * 10;
        },
        $values
    );

    var_dump($result);
    var_dump($seen);
}
?>
--EXPECT--
array(3) {
  [0]=>
  int(10)
  [1]=>
  int(20)
  [2]=>
  int(30)
}
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
}
