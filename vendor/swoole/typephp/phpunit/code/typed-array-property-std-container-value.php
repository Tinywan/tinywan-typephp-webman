<?php
class TypedArrayPropertyStdContainerValueBox
{
    #[StdList(Type::Int)]
    public array $values = [];
}
function typedArrayPropertyStdContainerValue(TypedArrayPropertyStdContainerValueBox $box): void
{
    $values = std::vector(Type::Int);
    $box->values[] = $values;
}
