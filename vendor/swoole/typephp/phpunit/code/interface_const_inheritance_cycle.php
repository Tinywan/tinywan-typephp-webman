<?php

interface ConstantCycleA extends ConstantCycleB
{
    const int A = 1;
}

interface ConstantCycleB extends ConstantCycleA
{
    const int B = 2;
}

function main(): void
{
}
