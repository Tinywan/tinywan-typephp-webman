<?php

class ReorderedConstructor
{
    #[Constructor]
    private string $name;

    #[Constructor]
    private int $id;

    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}
