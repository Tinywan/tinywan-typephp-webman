<?php

use native_types;

function switch_continue_in_for(): void
{
    for ($i = 0; $i < 2; $i++) {
        switch ($i) {
            case 0:
                continue;
            default:
                break;
        }
    }
}

function switch_continue_in_foreach(array $values): void
{
    foreach ($values as $value) {
        switch ($value) {
            case 0:
                continue;
            default:
                break;
        }
    }
}

function switch_continue_in_while(): void
{
    $i = 0;
    while ($i++ < 2) {
        switch ($i) {
            case 1:
                continue;
            default:
                break;
        }
    }
}

function switch_continue_in_do_while(): void
{
    $i = 0;
    do {
        switch ($i++) {
            case 0:
                continue;
            default:
                break;
        }
    } while ($i < 2);
}
