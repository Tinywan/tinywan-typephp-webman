<?php
interface I
{
    final const X = 1;
}

class P implements I {}

class C extends P
{
    const X = 2;
}

function main() {}
