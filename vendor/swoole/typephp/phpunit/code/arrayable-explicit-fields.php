<?php

#[Arrayable(fields: ['id', 'hidden'])]
class ArrayableExplicitFields
{
    private int $id = 1;
    protected array $hidden = [];
}
