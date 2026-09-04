<?php
class A
{
    const int X = 1;
}

class B extends A
{
    const int|string X = 2;
}

function main() {}
