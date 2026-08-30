<?php

#[Native]
class NativeMixedReturn {}

function makeNativeMixed(): mixed
{
    return new NativeMixedReturn();
}

function main(): void {}
