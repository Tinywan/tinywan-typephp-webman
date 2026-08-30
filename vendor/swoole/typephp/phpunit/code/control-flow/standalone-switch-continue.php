<?php

use native_types;

function standalone_switch_continue(int $value): void
{
    switch ($value) {
        case 1:
            continue;
        default:
            break;
    }
}
