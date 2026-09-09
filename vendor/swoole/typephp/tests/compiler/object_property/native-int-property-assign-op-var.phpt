--TEST--
Native int property compound assignment from var
--FILE--
<?php
use varint_types;

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
    $delta = std::any(2);
    $box->value += $delta;
    var_dump($box->value);

    $text = std::any("3");
    $box->value += $text;
    var_dump($box->value);

    $bad = std::any("abc");
    try {
        $box->value += $bad;
    } catch (TypeError $e) {
        var_dump($e::class);
    }

    $selfBox = new NativeIntAssignOpBox();
    $selfDelta = std::any(5);
    $selfBox->add($selfDelta);
    var_dump($selfBox->value);
}
?>
--EXPECT--
int(3)
int(6)
string(9) "TypeError"
int(6)
