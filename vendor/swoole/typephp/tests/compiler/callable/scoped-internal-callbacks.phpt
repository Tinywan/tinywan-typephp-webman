--TEST--
Internal callback functions preserve the declaring method scope
--FILE--
<?php

class ScopedInternalCallbacks
{
    private function triple(int $value): int
    {
        return $value * 3;
    }

    private static function replace(array $match): string
    {
        return strtoupper($match[0]);
    }

    private static function compareValue(mixed $left, mixed $right): int
    {
        return $left <=> $right;
    }

    private static function compareKey(mixed $left, mixed $right): int
    {
        return $left <=> $right;
    }

    public function run(): void
    {
        var_dump(array_map([$this, 'triple'], [1, 2, 3]));
        var_dump(array_map(array: [4, 5], callback: [$this, 'triple']));
        var_dump(preg_replace_callback_array([
            '/a+/' => [self::class, 'replace'],
            '/b+/' => static fn(array $match): string => '[' . $match[0] . ']',
        ], 'caaab'));
        var_dump(array_udiff_uassoc(
            ['a' => 1, 'b' => 2],
            ['a' => 1, 'c' => 2],
            [self::class, 'compareValue'],
            [self::class, 'compareKey'],
        ));

        $unpacked = [[$this, 'triple'], 6];
        var_dump(call_user_func(...$unpacked));
    }
}

function main(): void
{
    (new ScopedInternalCallbacks())->run();
}

?>
--EXPECT--
array(3) {
  [0]=>
  int(3)
  [1]=>
  int(6)
  [2]=>
  int(9)
}
array(2) {
  [0]=>
  int(12)
  [1]=>
  int(15)
}
string(7) "cAAA[b]"
array(1) {
  ["b"]=>
  int(2)
}
int(18)
