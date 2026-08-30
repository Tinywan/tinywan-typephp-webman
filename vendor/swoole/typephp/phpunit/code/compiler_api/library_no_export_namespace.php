<?php

namespace App\XX;

use \NoExport;
use NoExport as Hidden;

#[NoExport]
function imported_attribute(): int
{
    return 1;
}

#[Hidden]
function aliased_attribute(): int
{
    return 2;
}

#[\NoExport]
function fully_qualified_attribute(): int
{
    return 3;
}

#[namespace\NoExport]
function relative_attribute(): int
{
    return 4;
}

#[App\XX\NoExport]
function qualified_attribute(): int
{
    return 5;
}
