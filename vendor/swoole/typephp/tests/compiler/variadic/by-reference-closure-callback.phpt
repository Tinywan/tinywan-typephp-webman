--TEST--
Reference Closure parameters work at Zend callback boundaries used by Symfony mbstring polyfill
--FILE--
<?php

function convert_values(string $suffix, &...$vars): bool
{
    $ok = true;
    array_walk_recursive($vars, static function (&$value, $key) use (&$ok, $suffix): void {
        if (!is_string($value)) {
            $ok = false;
            return;
        }
        $value .= $suffix . ':' . $key;
    });
    return $ok;
}

function main(): void
{
    $first = ['a' => 'one', 'nested' => ['b' => 'two']];
    $second = 'three';
    var_dump(convert_values('!', $first, $second));
    var_dump($first, $second);

    $invalid = ['ok', 42];
    var_dump(convert_values('?', $invalid));
    var_dump($invalid);
}
?>
--EXPECT--
bool(true)
array(2) {
  ["a"]=>
  string(6) "one!:a"
  ["nested"]=>
  array(1) {
    ["b"]=>
    string(6) "two!:b"
  }
}
string(8) "three!:1"
bool(false)
array(2) {
  [0]=>
  string(5) "ok?:0"
  [1]=>
  int(42)
}
