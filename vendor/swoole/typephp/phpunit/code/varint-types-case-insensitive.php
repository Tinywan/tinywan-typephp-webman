<?php

use VaRiNt_TyPeS;

function varIntTypesCaseInsensitive(): void
{
    $integer = 42;
    $floating = 1.5;
    $boolean = true;
    var_dump($integer, $floating, $boolean);
}
