--TEST--
void and never expressions produce null in value contexts
--FILE--
<?php

function sink($value): void
{
    var_dump($value);
}

function main(): void
{
    $a = usleep(1);
    var_dump($a);

    $b = \usleep(1);
    var_dump($b);

    $flag = true && usleep(1);
    var_dump($flag);

    $items = [usleep(1)];
    var_dump($items);

    sink(usleep(1));

    var_dump("prefix" . usleep(1) . "suffix");
}
?>
--EXPECT--
NULL
NULL
bool(false)
array(1) {
  [0]=>
  NULL
}
NULL
string(12) "prefixsuffix"
