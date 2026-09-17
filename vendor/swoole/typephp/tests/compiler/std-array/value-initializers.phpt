--TEST--
All std containers infer their contracts from array value initializers
--FILE--
<?php
function typed_int(int $value): int
{
    return $value;
}

function typed_string(string $value): string
{
    return $value;
}

function next_value(int &$counter): int
{
    return ++$counter;
}

function main(): void
{
    $base = 2;
    $array = std::array([
        [1, $base + 1, typed_int(4)],
        [0, typed_int(2), 4],
    ]);
    var_dump(count($array), count($array[0]), $array[0][1], $array[1][1]);

    $vector = std::vector([typed_int(7), $base + 6, 9]);
    var_dump(count($vector), $vector[0], $vector[1], $vector[2]);

    $counter = 0;
    $evaluated = std::vector([next_value($counter), next_value($counter)]);
    var_dump($evaluated[0], $evaluated[1], $counter);

    $map = std::map([
        typed_string('v1') => typed_int(999),
        'v2' => $base + 998,
    ]);
    var_dump($map['v1'], $map['v2']);

    $intMap = std::map([
        typed_int(10) => typed_string('ten'),
        20 => 'twenty',
    ]);
    var_dump($intMap[10], $intMap[20]);

    $ordered = std::orderedMap([
        20 => typed_string('twenty'),
        typed_int(10) => 'ten',
    ]);
    var_dump($ordered[10], $ordered[20]);

    $list = std::list([typed_int(9), $base + 1, 5]);
    var_dump(is_array($list), $list[0], $list[1], $list[2]);

    $dict = std::dict([
        typed_string('v1') => typed_int(999),
        'v2' => $base + 998,
    ]);
    var_dump(is_array($dict), $dict['v1'], $dict['v2']);
}
?>
--EXPECT--
int(2)
int(3)
int(3)
int(2)
int(3)
int(7)
int(8)
int(9)
int(1)
int(2)
int(2)
int(999)
int(1000)
string(3) "ten"
string(6) "twenty"
string(3) "ten"
string(6) "twenty"
bool(true)
int(9)
int(3)
int(5)
bool(true)
int(999)
int(1000)
