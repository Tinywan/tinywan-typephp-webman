<?php
class ArrayDefStdContainerValueBox
{
    #[ArrayDef(Type::Any)]
    public array $values = [];
}
function arrayDefStdContainerValue(ArrayDefStdContainerValueBox $box): void
{
    $values = std::vector(Type::Int);
    $box->values[] = $values;
}
