<?php

function optimizerDynamicBool(): mixed
{
    return true;
}

function optimizerTypedBool(): bool
{
    return true;
}

function optimizerTypedInt(): int
{
    return 1;
}

function optimizerTypedFloat(): float
{
    return 0.0;
}

function optimizerTypedArgumentCalls(): void
{
    in_array('1', [1], optimizerTypedBool());
    hypot(optimizerTypedInt(), optimizerTypedFloat());
    in_array('1', [1], optimizerTypedInt());
    in_array('1', [1], optimizerDynamicBool());
    strlen(null);
    json_decode('null', null);
    floor('1.5');
    round('1.25');
    floor(1.5);
    round(1.25);
}
