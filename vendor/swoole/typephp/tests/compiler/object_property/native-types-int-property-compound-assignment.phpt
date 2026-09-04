--TEST--
Native-types int property compound assignments use C++ integer semantics
--FILE--
<?php
use native_types;

class NativeTypesIntCompoundBox
{
    public int $value = 0;
}

function nativeTypesIntCompoundReceiver(NativeTypesIntCompoundBox $box, int &$calls): NativeTypesIntCompoundBox
{
    $calls++;
    return $box;
}

function nativeTypesIntCompoundOperand(int $value, int &$calls): int
{
    $calls++;
    return $value;
}

function main(): void
{
    $box = new NativeTypesIntCompoundBox();
    $receiverCalls = 0;
    $operandCalls = 0;

    $box->value = 7;
    $result = nativeTypesIntCompoundReceiver($box, $receiverCalls)->value
        /= nativeTypesIntCompoundOperand(2, $operandCalls);
    var_dump($result, $box->value, $receiverCalls, $operandCalls);

    $result = nativeTypesIntCompoundReceiver($box, $receiverCalls)->value *= 3;
    var_dump($result, $box->value, $receiverCalls);
}
?>
--EXPECT--
int(3)
int(3)
int(1)
int(1)
int(9)
int(9)
int(2)
