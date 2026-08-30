--TEST--
typed array/string reads and shorthand ternaries preserve PHP semantics
--FILE--
<?php

function inspectTypedReads(array $values, string $key, string $text, int $offset): void
{
    var_dump($values['value']);
    var_dump($values[$key]);
    var_dump($text[0]);
    var_dump($text[$offset]);
    var_dump($text[-1]);
}

function inspectSelections(array $values, string $text, bool $flag): void
{
    var_dump($values ?: ['fallback']);
    var_dump($text ?: 'fallback');
    var_dump($flag ?: 42);
}

function main(): void
{
    $referenced = 3;
    $values = [
        'value' => 1,
        '12' => 'numeric key',
        'ref' => &$referenced,
    ];

    inspectTypedReads($values, '12', 'test', 1);

    $copy = $values['ref'];
    $copy = 4;
    var_dump($referenced, $copy);

    inspectSelections($values, '0', false);
    inspectSelections([], 'ok', true);
}
?>
--EXPECT--
int(1)
string(11) "numeric key"
string(1) "t"
string(1) "e"
string(1) "t"
int(3)
int(4)
array(3) {
  ["value"]=>
  int(1)
  [12]=>
  string(11) "numeric key"
  ["ref"]=>
  &int(3)
}
string(8) "fallback"
int(42)
array(1) {
  [0]=>
  string(8) "fallback"
}
string(2) "ok"
bool(true)
