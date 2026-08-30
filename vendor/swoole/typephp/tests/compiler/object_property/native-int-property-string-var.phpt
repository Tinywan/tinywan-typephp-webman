--TEST--
Native int property assignment rejects string var in strict mode
--FILE--
<?php
class NativeIntStringVarBox
{
    public int $value = 0;
}

function main(): void
{
    $box = new NativeIntStringVarBox();

    $numeric = any("123");
    try {
        $box->value = $numeric;
    } catch (TypeError $e) {
        var_dump($e->getMessage());
    }

    $bad = any("abc");
    try {
        $box->value = $bad;
    } catch (TypeError $e) {
        var_dump($e->getMessage());
    }
}
?>
--EXPECT--
string(74) "Cannot assign string to property NativeIntStringVarBox::$value of type int"
string(74) "Cannot assign string to property NativeIntStringVarBox::$value of type int"
