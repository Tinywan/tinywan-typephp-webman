--TEST--
array_walk() tests
--SKIPIF--
<?php die("skip AOT array_walk differs from PHP"); ?>
--FILE--
<?php
function foo($v1, $v2, $v3)
{
    var_dump($v1);
    var_dump($v2);
    var_dump($v3);
}
function foo2($v1, $v2, $v3)
{
    throw new Exception($v3);
}
function main()
{
    $var = array(1, 2);
    var_dump(array_walk($var, "foo", "data"));
    try {
        var_dump(array_walk($var, "foo2", "data"));
    } catch (Exception $e) {
        var_dump($e->getMessage());
    }
    echo "Done\n";
}
?>
--EXPECT--
int(1)
int(0)
string(4) "data"
int(2)
int(1)
string(4) "data"
bool(true)
string(4) "data"
Done
