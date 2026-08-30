--TEST--
static closure use by reference through typed Closure parameter
--FILE--
<?php
function apply_items(iterable $items, Closure $callback): array
{
    $out = [];
    foreach ($items as $item) {
        $out[] = $callback($item);
    }
    return $out;
}

function main(): void
{
    $seen = [];
    $prefix = 'v';

    $result = apply_items(
        [1, 2, 3],
        static function (int $value) use (&$seen, $prefix): string {
            $seen[] = $value;
            return $prefix . ($value * 10);
        }
    );

    var_dump($result);
    var_dump($seen);
}
?>
--EXPECT--
array(3) {
  [0]=>
  string(3) "v10"
  [1]=>
  string(3) "v20"
  [2]=>
  string(3) "v30"
}
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
}
