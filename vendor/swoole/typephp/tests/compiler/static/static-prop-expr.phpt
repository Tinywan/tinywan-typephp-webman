--TEST--
Static Class Property Read/Write Test
--FILE--
<?php
final class A
{
    public static array $a = [
        'A' => __DIR__ . '/../../A',
    ];
}

function main(): void
{
    var_dump(A::$a['A']);
}
?>
--EXPECTF--
string(%d) "%s/compiler/static/../../A"
