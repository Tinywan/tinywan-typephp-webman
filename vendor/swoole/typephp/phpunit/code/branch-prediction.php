<?php

function phpunit_branch_prediction(bool $likely, mixed $unlikely): int
{
    if (expected($likely)) {
        return 1;
    }
    if (unexpected((bool) $unlikely)) {
        return 2;
    }
    return 3;
}
