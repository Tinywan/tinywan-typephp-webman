<?php
class ArrayDefClassMapKeyType {}
class ArrayDefClassMapKeyBox
{
    #[ArrayDef(ArrayDefClassMapKeyType::class, Type::String)]
    public array $value = [];
}
