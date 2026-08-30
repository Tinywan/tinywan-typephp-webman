<?php

#[Native]
class NativeSwitchOperand {}

function main(): void
{
    $value = new NativeSwitchOperand();
    switch ($value) {
        case null:
            break;
    }
}
