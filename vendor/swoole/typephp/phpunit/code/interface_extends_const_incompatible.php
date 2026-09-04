<?php
interface I
{
    const int X = 1;
}

interface J extends I
{
    const string X = 'a';
}

function main() {}
