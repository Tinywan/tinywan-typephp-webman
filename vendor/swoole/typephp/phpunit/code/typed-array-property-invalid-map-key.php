<?php
class TypedArrayPropertyInvalidMapKey
{
    #[StdDict(Type::Bool, Type::String)]
    public array $value = [];
}
