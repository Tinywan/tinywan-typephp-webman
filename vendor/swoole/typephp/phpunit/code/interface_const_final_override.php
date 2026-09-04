<?php
interface I
{
    final const X = 1;
}

class C implements I
{
    const X = 2;
}

function main() {}
