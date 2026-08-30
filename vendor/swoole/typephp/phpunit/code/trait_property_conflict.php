<?php
trait TraitPropertyConflict
{
    public int $count = 1;
}

class TraitPropertyConflictUser
{
    use TraitPropertyConflict;

    public int $count = 2;
}

function main() {}
