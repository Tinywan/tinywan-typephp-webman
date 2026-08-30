--TEST--
Native class: clone accepts rooted native-producing expressions
--FILE--
<?php

#[Native]
class NativeCloneExpressionValue
{
    public int $number = 40;

    public function __clone(): void
    {
        $this->number++;
    }
}

#[Native]
class NativeCloneExpressionHolder
{
    public ?NativeCloneExpressionValue $value;
}

function makeNativeCloneExpressionValue(): NativeCloneExpressionValue
{
    return new NativeCloneExpressionValue();
}

function main(): void
{
    $fromCall = clone makeNativeCloneExpressionValue();
    var_dump($fromCall->number);

    $holder = new NativeCloneExpressionHolder();
    $holder->value = makeNativeCloneExpressionValue();
    $fromProperty = clone $holder->value;
    var_dump($fromProperty->number);
}
?>
--EXPECT--
int(41)
int(41)
