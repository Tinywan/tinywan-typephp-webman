--TEST--
Closure use reference capture through callbacks
--FILE--
<?php
function apply_items(array $items, callable $cb): string
{
    $out = '';
    foreach ($items as $item) {
        $result = $cb($item);
        if ($result !== null) {
            $out .= $result;
        }
    }
    return $out;
}

function main(): void
{
    $errors = [];
    array_map(function ($value) use (&$errors) {
        if ($value % 2 === 0) {
            $errors[] = "even:$value";
        }
        return null;
    }, [1, 2, 3, 4]);
    var_dump($errors);

    $generated = [];
    $code = apply_items([1, 2, 3], function ($value) use (&$generated) {
        $generated[] = $value * 10;
        return "[$value]";
    });
    var_dump($code);
    var_dump($generated);

    $declaredStrings = [];
    $emit = function (string $name) use (&$declaredStrings): string {
        if (isset($declaredStrings[$name])) {
            return $declaredStrings[$name];
        }
        $declaredStrings[$name] = 's_' . count($declaredStrings);
        return $declaredStrings[$name];
    };
    var_dump($emit('alpha'));
    var_dump($emit('beta'));
    var_dump($emit('alpha'));
    var_dump($declaredStrings);
}
?>
--EXPECT--
array(2) {
  [0]=>
  string(6) "even:2"
  [1]=>
  string(6) "even:4"
}
string(9) "[1][2][3]"
array(3) {
  [0]=>
  int(10)
  [1]=>
  int(20)
  [2]=>
  int(30)
}
string(3) "s_0"
string(3) "s_1"
string(3) "s_0"
array(2) {
  ["alpha"]=>
  string(3) "s_0"
  ["beta"]=>
  string(3) "s_1"
}
