<?php

class ResultFactory
{
    #[MustUse]
    public function create(): int
    {
        return 1;
    }
}

function main(): void
{
    $factory = new ResultFactory();
    $factory->create();
}
