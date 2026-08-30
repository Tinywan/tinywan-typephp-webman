<?php
trait TraitConstantConflict
{
    public const VALUE = 1;
}

class TraitConstantConflictUser
{
    use TraitConstantConflict;

    public const VALUE = 2;
}

function main() {}
