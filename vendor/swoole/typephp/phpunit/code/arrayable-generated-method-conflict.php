<?php

#[Arrayable]
class ArrayableGeneratedMethodConflict
{
    public string $name = 'TypePHP';

    public function toArray(): array
    {
        return ['name' => $this->name];
    }
}
