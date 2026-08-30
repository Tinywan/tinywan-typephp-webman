<?php

trait MissingParentTrait
{
    public function execute(): void
    {
        parent::execute();
    }
}

class NoParentClass
{
    use MissingParentTrait;
}

function main(): void
{
}
