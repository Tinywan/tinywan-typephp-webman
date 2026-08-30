--TEST--
Variadic union and nullable parameter runtime type checking
--FILE--
<?php
function collect_scalars(int|string ...$values): array
{
    return $values;
}

function collect_nullable(?int ...$values): array
{
    return $values;
}

function main(): void
{
    var_dump(collect_scalars(1, "two", named: 3));
    var_dump(collect_nullable(1, null, 3));

    $errors = [];
    try {
        collect_scalars(1, "two", any([]));
    } catch (\TypeError $e) {
        $errors[] = $e->getMessage();
    }
    try {
        collect_nullable(ok: 1, bad: any("x"));
    } catch (\TypeError $e) {
        $errors[] = $e->getMessage();
    }

    foreach ($errors as $error) {
        var_dump($error);
    }
}
?>
--EXPECT--
array(3) {
  [0]=>
  int(1)
  [1]=>
  string(3) "two"
  ["named"]=>
  int(3)
}
array(3) {
  [0]=>
  int(1)
  [1]=>
  NULL
  [2]=>
  int(3)
}
string(80) "collect_scalars(): Argument #3 ($values) must be of type int|string, array given"
string(76) "collect_nullable(): Argument #2 ($values) must be of type ?int, string given"
