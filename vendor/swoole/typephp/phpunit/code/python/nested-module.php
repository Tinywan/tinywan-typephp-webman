<?php

use python\numpy\linalg as linalg;

function pythonNestedModule(): mixed
{
    return linalg\norm([3, 4]);
}

function main(): void
{
}
