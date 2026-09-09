--TEST--
std::any() is available in arbitrary expression positions
--FILE--
<?php

function return_any(int $value): mixed
{
    return std::any($value);
}

function main(): void
{
    var_dump(std::any(1));
    var_dump(return_any(2));
    var_dump([std::any(3), std::any("four")]);
    var_dump(std::any(4) + 1);
    var_dump(true ? std::any(5) : std::any(6));
    var_dump(std::any(std::any(7)));
}
?>
--EXPECT--
int(1)
int(2)
array(2) {
  [0]=>
  int(3)
  [1]=>
  string(4) "four"
}
int(5)
int(5)
int(7)
