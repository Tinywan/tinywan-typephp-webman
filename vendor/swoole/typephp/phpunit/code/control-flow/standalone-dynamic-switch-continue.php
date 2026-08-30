<?php

function standalone_dynamic_switch_continue(mixed $value): void
{
    switch ($value) {
        case 1:
            continue;
        default:
            break;
    }
}
