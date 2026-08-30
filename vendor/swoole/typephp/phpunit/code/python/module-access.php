<?php

use Python\numpy as np;
use python\unused;

function pythonModuleVersion()
{
    return np\version;
}

function pythonModuleArray()
{
    return np\array([1, 2, 3]);
}

function main(): void
{
}
