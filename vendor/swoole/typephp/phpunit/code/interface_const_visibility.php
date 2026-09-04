<?php
interface I
{
    const X = 1;
}

class C implements I
{
    protected const X = 1;
}

function main() {}
