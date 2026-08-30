<?php

use native_types;

function localLiteralDeclarationInitializerNative(): void
{
    $integer = 42;
    $negative = -7;
    $floating = 1.25;
    $boolean = true;
    $string = 'hello';
    $nullValue = null;

    var_dump($integer, $negative, $floating, $boolean, $string, $nullValue);
}
