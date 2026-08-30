--TEST--
SSA narrowing: mixed int/float types stay Var
--FILE--
<?php
function main(): void {
    // Mixed definitions: int then float → stays Var
    $a = 10;
    $a = 3.14;
    var_dump($a);

    // Float then int → stays Var
    $b = 2.5;
    $b = 99;
    var_dump($b);

    // Int compound assigns still produce the expected runtime value,
    // but SSA no longer uses them to prove native int narrowing.
    $c = 10;
    $c += 5;
    $c *= 3;
    var_dump($c);
}
?>
--EXPECT--
float(3.14)
int(99)
int(45)
