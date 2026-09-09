<?php

use varint_types;

function foreachKeyVarIntReuse(array $values): void
{
    $index = 100;
    foreach ($values as $index => $value) {
        var_dump($value);
    }
}
