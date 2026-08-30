<?php

use native_types;

class NativeMethodUnsetKeepsOptimization
{
    public int $value = 7;

    public function read(): int
    {
        return $this->value;
    }
}

function nativeMethodUnsetKeepsOptimization(int $branch): int
{
    $object = new NativeMethodUnsetKeepsOptimization();
    if ($branch === 1) {
        unset($object);
    } elseif ($branch === 2) {
        var_dump($object->value);
    } else {
        var_dump($object->value);
    }

    return $object->read();
}
