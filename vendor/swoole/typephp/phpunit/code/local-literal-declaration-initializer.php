<?php

function localLiteralDeclarationInitializer(): void
{
    $integer = 42;
    $negative = -7;
    $floating = 1.25;
    $boolean = true;
    $string = 'hello';
    $nullValue = null;

    if ($boolean) {
        $nested = 9;
    }

    $computed = 40 + 2;

    var_dump(
        $integer,
        $negative,
        $floating,
        $boolean,
        $string,
        $nullValue,
        $nested,
        $computed,
    );
}
