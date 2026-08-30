--TEST--
extract() into typed references must respect their type
--SKIPIF--
<?php
if (true) die("skip AOT does not support extract()");
?>

--FILE--
<?php
class Test
{
    public int $i = 0;
    public string $s = "";
}
function main()
{
    $test = new Test();
    $i =& $test->i;
    $s =& $test->s;
    try {
        extract(['i' => 'foo', 's' => 42]);
    } catch (TypeError $e) {
        echo $e->getMessage(), "\n";
    }
    var_dump($test->i, $test->s);
}
?>
--EXPECT--
Cannot assign string to reference held by property Test::$i of type int
int(0)
string(0) ""
