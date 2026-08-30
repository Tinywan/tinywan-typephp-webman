<?php
trait TraitMemberCompatible
{
    public const VALUE = 1;
    public int $count = 1;
}

class TraitMemberCompatibleUser
{
    use TraitMemberCompatible;

    public const VALUE = 1;
    public int $count = 1;
}

function main() {}
