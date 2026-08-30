--TEST--
next - ensure warning is received when passing an indirect temporary.
--SKIPIF--
<?php die("skip AOT next() error handling differs from PHP"); ?>
--FILE--
<?php
function f()
{
    return array(1, 2);
}
function main()
{
    var_dump(next(f()));
}
?>
--EXPECTF--
Notice: Only variables should be passed by reference in %s on line %d
int(2)
