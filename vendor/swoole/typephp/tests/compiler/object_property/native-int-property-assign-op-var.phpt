--TEST--
Native int property compound assignment from var
--FILE--
<?php
class NativeIntAssignOpBox
{
    public int $value = 1;

    public function add($delta): void
    {
        $this->value += $delta;
    }
}

function main(): void
{
    $box = new NativeIntAssignOpBox();
    $delta = any(2);
    $box->value += $delta;
    var_dump($box->value);

    $text = any("3");
    try {
        $box->value += $text;
    } catch (TypeError $e) {
        var_dump($e->getMessage());
    }

    $bad = any("abc");
    try {
        $box->value += $bad;
    } catch (TypeError $e) {
        var_dump($e->getMessage());
    }

    $selfBox = new NativeIntAssignOpBox();
    $selfDelta = any(5);
    $selfBox->add($selfDelta);
    var_dump($selfBox->value);
}
?>
--EXPECT--
int(3)
string(73) "Cannot assign string to property NativeIntAssignOpBox::$value of type int"
string(73) "Cannot assign string to property NativeIntAssignOpBox::$value of type int"
int(6)
