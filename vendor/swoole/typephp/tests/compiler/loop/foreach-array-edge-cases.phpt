--TEST--
foreach handles sparse mixed arrays, evaluates sources once, and releases cursors on control flow
--FILE--
<?php

final class ForeachArraySource
{
    public static int $calls = 0;

    public static function values(): array
    {
        ++self::$calls;
        return [0 => 'zero', 3 => 'three', 'name' => 'value'];
    }
}

function stopEarly(array $values): string
{
    foreach ($values as $value) {
        return $value;
    }
    return 'empty';
}

function main(): void
{
    $seen = [];
    foreach (ForeachArraySource::values() as $key => $value) {
        if ($key === 3) {
            continue;
        }
        $seen[$key] = $value;
    }
    var_dump(ForeachArraySource::$calls, $seen);

    $empty = [];
    foreach ($empty as $value) {
        echo "unreachable\n";
    }

    $inner = 1;
    $references = [&$inner];
    foreach ($references as $value) {
        $value = 9;
    }
    var_dump($inner);

    foreach ($references as &$value) {
        $value = 11;
    }
    unset($value);
    var_dump($inner);
    var_dump(stopEarly(['first', 'second']));

    try {
        foreach (new ArrayIterator([1, 2]) as $value) {
            throw new RuntimeException('stop');
        }
    } catch (RuntimeException $exception) {
        echo $exception->getMessage(), "\n";
    }
}
?>
--EXPECT--
int(1)
array(2) {
  [0]=>
  string(4) "zero"
  ["name"]=>
  string(5) "value"
}
int(1)
int(11)
string(5) "first"
stop
