<?php

function foreachKeyAnyReuse(array $values): void
{
    $index = std::any(100);
    foreach ($values as $index => $value) {
        var_dump($value);
    }
}
