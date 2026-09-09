<?php

class NativePropertyAssignOpClassConstBox
{
    public int $flags = 7;

    public function clearPublic(): int
    {
        $this->flags &= ~ReflectionClassConstant::IS_PUBLIC;
        return $this->flags;
    }
}

function native_property_assign_op_class_const(): int
{
    $box = new NativePropertyAssignOpClassConstBox();
    return $box->clearPublic();
}
