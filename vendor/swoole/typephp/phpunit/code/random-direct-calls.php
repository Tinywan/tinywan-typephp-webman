<?php

function randomDirectCalls(): array
{
    return [
        mt_rand(),
        mt_rand(1, 10),
        rand(),
        rand(10, 1),
        random_int(1, 10),
        random_bytes(16),
        mt_getrandmax(),
        getrandmax(),
    ];
}
