<?php

function foreachKeyNativeReuse(array $values): void
{
    $index = 100;
    foreach ($values as $index => $value) {
        var_dump($value);
    }
}
