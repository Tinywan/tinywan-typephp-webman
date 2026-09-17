<?php
class TypedArrayPropertyClassMapKeyType {}
class TypedArrayPropertyClassMapKeyBox
{
    #[StdDict(TypedArrayPropertyClassMapKeyType::class, Type::String)]
    public array $value = [];
}
