<?php

#[Native]
class NativeSwitchCase {}

function main(): void
{
    $value = new NativeSwitchCase();
    switch (1) {
        case $value:
            break;
    }
}
