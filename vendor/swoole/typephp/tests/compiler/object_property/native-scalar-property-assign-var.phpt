--TEST--
Native scalar object property assignment checks var RHS before native write
--FILE--
<?php
class NativeScalarAssignVarBox
{
    public int $intValue = 0;
    public float $floatValue = 0.0;
    public bool $boolValue = false;
    public string $stringValue = '';
}

function main(): void
{
    $box = new NativeScalarAssignVarBox();

    $intValue = std::any(12);
    $box->intValue = $intValue;

    $floatValue = std::any(3.5);
    $box->floatValue = $floatValue;

    $boolValue = std::any(false);
    $box->boolValue = $boolValue;

    $stringValue = std::any("123");
    $box->stringValue = $stringValue;

    var_dump($box->intValue);
    var_dump($box->floatValue);
    var_dump($box->boolValue);
    var_dump($box->stringValue);

    try {
        $badIntValue = std::any("12");
        $box->intValue = $badIntValue;
    } catch (TypeError $e) {
        var_dump($e->getMessage());
    }
}
?>
--EXPECT--
int(12)
float(3.5)
bool(false)
string(3) "123"
string(80) "Cannot assign string to property NativeScalarAssignVarBox::$intValue of type int"
