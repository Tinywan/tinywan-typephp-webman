<?php

function phpunit_branch_prediction(bool $likely, mixed $unlikely): int
{
    if (std::expected($likely)) {
        return 1;
    }
    if (std::unexpected((bool) $unlikely)) {
        return 2;
    }
    return 3;
}
