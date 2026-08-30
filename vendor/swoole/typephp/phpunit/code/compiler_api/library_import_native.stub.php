<?php

namespace LibraryApi;

use \NoExport;

class NativeCounter
{
    public const int INITIAL = 3;
    public int $value = self::INITIAL;

    public function bump(int $amount): int {}
}

function native_value(string $name = 'typephp'): string {}

#[NoExport]
function native_hidden(): int {}
