--TEST--
Test extract() function (variation 9)
--SKIPIF--
<?php
if (true) die("skip AOT does not support extract()");
?>

--FILE--
<?php
class classA
{
    public $v;
}
function main()
{
    /* Using Class and objects */
    echo "\n*** Testing for object ***\n";
    $A = new classA();
    var_dump(extract(get_object_vars($A), EXTR_REFS));
    echo "Done\n";
}
?>
--EXPECT--
*** Testing for object ***
int(1)
Done
