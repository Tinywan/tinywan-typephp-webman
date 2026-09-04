--TEST--
optimized builtin calls preserve argument unpacking
--FILE--
<?php
function main(): void
{
    $intvalArgs = ['ff', 16];
    $roundArgs = [2.5, 0, PHP_ROUND_HALF_DOWN];
    $nullArgs = [null];
    $arrayKeysArgs = [['a' => 1]];
    $functionExistsArgs = ['strlen'];

    var_dump(intval(...$intvalArgs));
    var_dump(round(...$roundArgs));
    var_dump(is_null(...$nullArgs));
    var_dump(array_keys(...$arrayKeysArgs));
    var_dump(function_exists(...$functionExistsArgs));
}
?>
--EXPECT--
int(255)
float(2)
bool(true)
array(1) {
  [0]=>
  string(1) "a"
}
bool(true)
