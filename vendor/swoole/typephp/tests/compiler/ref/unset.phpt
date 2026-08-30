--TEST--
object link operator
--FILE--
<?php
function main()
{
    $a = 1;
    $b = &$a;
    $b = 2;
    var_dump($a, $b);
    unset($b);
    var_dump($a);
    var_dump(isset($b));
    // var_dump($b ?? 123); // 修复前这个报错

    $b = 1;
    var_dump($b, $a);
}
?>
--EXPECT--
int(2)
int(2)
int(2)
bool(false)
int(1)
int(2)