<?php
#[Native]
class ArrayDefNativeValue {}
class ArrayDefNativeValueBox
{
    #[ArrayDef(ArrayDefNativeValue::class)]
    public array $values = [];
}
