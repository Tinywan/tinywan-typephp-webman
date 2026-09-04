<?php
interface I
{
    final const X = 1;
}

enum E implements I
{
    const X = 2;
    case A;
}

function main() {}
