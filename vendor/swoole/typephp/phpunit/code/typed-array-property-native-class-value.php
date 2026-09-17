<?php
#[Native]
class TypedArrayPropertyNativeValue {}
class TypedArrayPropertyNativeValueBox
{
    #[StdList(TypedArrayPropertyNativeValue::class)]
    public array $values = [];
}
