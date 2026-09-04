<?php

use native_types;

class NativePropertyAssignOpIntBox
{
    public int $value = 1;

    public function addThis(): int
    {
        $this->value += 2;
        return $this->value;
    }
}

function native_property_assign_op_int_object(): int
{
    $box = new NativePropertyAssignOpIntBox();
    $box->value += 2;
    return $box->value;
}
