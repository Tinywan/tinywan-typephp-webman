<?php

function generatedCodeIndentation(array $items): int
{
    foreach ($items as $item) {
        try {
            if ($item) {
                return 1;
            } elseif ($item === 0) {
                return 0;
            } else {
                continue;
            }
        } catch (RuntimeException) {
            return 2;
        }
    }

    return -1;
}

function generated_empty_function()
{
}

function generated_implicit_return()
{
    $value = 1;
}
