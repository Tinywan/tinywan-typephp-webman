<?php
interface I1
{
    const X = 1;
}

interface I2
{
    const X = 1;
}

class C implements I1, I2 {}

function main() {}
