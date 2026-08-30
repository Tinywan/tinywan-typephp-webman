<?php

#[Native]
class NativeWithoutToAny
{
}

function main(): void
{
    (new NativeWithoutToAny())->toAny();
}
