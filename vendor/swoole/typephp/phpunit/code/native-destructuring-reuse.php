<?php

class NativeDestructuringBox
{
    public int $value = 0;
}

function nativeDestructuringReuse(array $values): void
{
    $index = 100;
    [$index] = $values;

    $box = new NativeDestructuringBox();
    [$box->value] = $values;
}
