<?php

interface TraitConstantContract
{
    const int VALUE = 1;
}

trait IncompatibleConstantTrait
{
    const string VALUE = 'wrong';
}

class TraitConstantImplementation implements TraitConstantContract
{
    use IncompatibleConstantTrait;
}

function main(): void
{
}
