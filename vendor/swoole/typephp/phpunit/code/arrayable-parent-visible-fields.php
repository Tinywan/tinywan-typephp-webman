<?php

class ArrayableParentVisibleFieldsBase
{
    public int $id = 1;
    protected string $name = 'TypePHP';
}

#[Arrayable(fields: ['id', 'name', 'local'])]
class ArrayableParentVisibleFieldsChild extends ArrayableParentVisibleFieldsBase
{
    private array $local = [];
}
