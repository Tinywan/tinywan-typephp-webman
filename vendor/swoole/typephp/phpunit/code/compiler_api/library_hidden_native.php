<?php

#[NoExport]
#[Native]
final class LibraryHiddenNative
{
    public int $value = 0;
}

function library_visible_value(): int
{
    return 42;
}
