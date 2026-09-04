<?php
interface I
{
    const int X = 1;
}

class C implements I
{
    const string X = 'a';
}

function main() {}
