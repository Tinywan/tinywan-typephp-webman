--TEST--
Native scalar object property compound assignment checks var RHS before native write
--FILE--
<?php
class NativeScalarAssignOpVarBox
{
    public int $intValue = 1;
    public float $floatValue = 1.5;

    public function addInside($intDelta, $floatDelta): void
    {
        $this->intValue += $intDelta;
        $this->floatValue += $floatDelta;
    }
}

function main(): void
{
    $box = new NativeScalarAssignOpVarBox();

    $intDelta = std::any(2);
    $box->intValue += $intDelta;

    $floatDelta = std::any(2.25);
    $box->floatValue += $floatDelta;

    var_dump($box->intValue);
    var_dump($box->floatValue);

    $methodIntDelta = std::any(3);
    $methodFloatDelta = std::any(0.25);
    $box->addInside($methodIntDelta, $methodFloatDelta);
    var_dump($box->intValue);
    var_dump($box->floatValue);

    try {
        $badIntDelta = std::any("4");
        $box->intValue += $badIntDelta;
    } catch (TypeError $e) {
        var_dump($e->getMessage());
    }
}
?>
--EXPECT--
int(3)
float(3.75)
int(6)
float(4)
string(82) "Cannot assign string to property NativeScalarAssignOpVarBox::$intValue of type int"
